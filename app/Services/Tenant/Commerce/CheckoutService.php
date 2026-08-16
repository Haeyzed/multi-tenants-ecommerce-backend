<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\CartStatus;
use App\Enums\Tenant\Commerce\CheckoutSessionStatus;
use App\Enums\Tenant\Commerce\FulfillmentStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Cart;
use App\Models\Tenant\CartItem;
use App\Models\Tenant\CheckoutSession;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\SellerOffer;
use App\Models\Tenant\ShippingMethod;
use App\Services\Landlord\Feature\FeatureGate;
use App\Services\Landlord\Feature\UsageLimiter;
use App\Services\Tenant\Accounting\AccountingService;
use App\Services\Tenant\Marketplace\CommissionService;
use App\Services\Tenant\Marketplace\SellerOrderService;
use App\Services\Tenant\Tax\TaxService;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Converts an active cart into a sales order.
 */
class CheckoutService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly CartService $cartService,
        private readonly CommerceSettingService $commerceSettings,
        private readonly CommissionService $commissions,
        private readonly DiscountService $discountService,
        private readonly FeatureGate $featureGate,
        private readonly FlashSaleService $flashSaleService,
        private readonly GiftCardService $giftCardService,
        private readonly OrderInventoryService $orderInventory,
        private readonly SellerOrderService $sellerOrders,
        private readonly StoreCreditService $storeCreditService,
        private readonly TaxService $taxService,
        private readonly UsageLimiter $usageLimiter,
    ) {}

    /**
     * Checkout the customer's active cart.
     *
     * Gift card and store credit are prepaid tenders applied once discounts, tax and
     * shipping have been settled: the gift card is drawn down first, then store credit.
     * The order's `grand_total` therefore records only what is still owed through a
     * payment gateway, while `gift_card_amount` and `store_credit_amount` snapshot the
     * prepaid portions so a refund can restore each to its original source.
     *
     * @param  array{
     *     shipping_address_id: int,
     *     billing_address_id?: int|null,
     *     shipping_method_id?: int|null,
     *     coupon_code?: string|null,
     *     loyalty_points?: int|null,
     *     gift_card_code?: string|null,
     *     store_credit_amount?: string|float|null,
     *     idempotency_key?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function checkout(Customer $customer, array $data): Order
    {
        return DB::transaction(function () use ($customer, $data): Order {
            $tenant = tenant();
            if ($tenant instanceof Tenant && $tenant->activeSubscription() !== null) {
                $this->usageLimiter->assertCanCreate('orders', $tenant);
            }

            $idempotencyKey = $data['idempotency_key'] ?? null;

            if (is_string($idempotencyKey) && $idempotencyKey !== '') {
                $existing = $this->findIdempotentOrder($customer, $idempotencyKey);

                if ($existing !== null) {
                    return $existing->load(['items', 'customer', 'shippingMethod']);
                }
            }

            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->where('status', CartStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($cart === null) {
                throw ValidationException::withMessages([
                    'cart' => 'No active cart found.',
                ]);
            }

            $cart->load(['items.product', 'items.productVariant', 'items.sellerOffer.seller']);

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Cart is empty.',
                ]);
            }

            foreach ($cart->items as $item) {
                $this->cartService->revalidateItem($item, $cart->currency);
            }

            $cart->refresh()->load(['items.product', 'items.productVariant', 'items.sellerOffer.seller']);

            $shippingAddress = $this->resolveCustomerAddress(
                $customer,
                (int) $data['shipping_address_id'],
                'shipping_address_id',
            );

            $billingAddress = null;
            if (! empty($data['billing_address_id'])) {
                $billingAddress = $this->resolveCustomerAddress(
                    $customer,
                    (int) $data['billing_address_id'],
                    'billing_address_id',
                );
            }

            $totals = $this->cartService->totals($cart);
            $subtotal = $totals['subtotal'];
            $shippingTotal = '0.00';
            $shippingMethod = null;

            if (! empty($data['shipping_method_id'])) {
                $shippingMethod = ShippingMethod::query()
                    ->whereKey((int) $data['shipping_method_id'])
                    ->where('is_active', true)
                    ->first();

                if ($shippingMethod === null) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' => 'The selected shipping method is invalid.',
                    ]);
                }

                if (
                    $shippingMethod->min_order_amount !== null
                    && bccomp($subtotal, (string) $shippingMethod->min_order_amount, 2) < 0
                ) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' => 'Order subtotal does not meet the shipping method minimum.',
                    ]);
                }

                $shippingTotal = (string) $shippingMethod->amount;
            }

            $couponCode = isset($data['coupon_code']) && is_string($data['coupon_code'])
                ? $data['coupon_code']
                : null;

            $discountApplication = $this->discountService->applyCouponsAndPromotions(
                $customer,
                $cart,
                $couponCode,
                $subtotal,
                $shippingTotal,
            );

            $discountApplication = $this->discountService->applyLoyaltyRedemption(
                $customer,
                $cart,
                $discountApplication,
                $subtotal,
                isset($data['loyalty_points']) ? (int) $data['loyalty_points'] : null,
            );

            $discountTotal = $discountApplication->discountTotal;
            $shippingTotal = $discountApplication->shippingTotal;
            $lineDiscountMap = collect($discountApplication->lineDiscounts);

            $taxLines = $cart->items->map(function (CartItem $item) use ($lineDiscountMap): array {
                $lineSubtotal = (string) $item->subtotal;
                $lineDiscount = (string) ($lineDiscountMap->get($item->id) ?? '0.00');
                $taxableAmount = Money::sub($lineSubtotal, $lineDiscount);

                if (bccomp($taxableAmount, '0', 2) < 0) {
                    $taxableAmount = '0.00';
                }

                return [
                    'key' => $item->id,
                    'amount' => $taxableAmount,
                ];
            })->all();

            $address = [
                'country_id' => $shippingAddress->country_id,
                'state_id' => $shippingAddress->state_id,
                'city_id' => $shippingAddress->city_id,
            ];

            $taxResult = $this->taxService->calculateOrderTax($taxLines, $shippingTotal, $address);
            $taxTotal = $taxResult['tax_total'];
            $lineTaxMap = collect($taxResult['line_taxes'])->keyBy('key');

            $grandTotal = Money::add(
                Money::add(Money::sub($subtotal, $discountTotal), $taxTotal),
                $shippingTotal,
            );

            $prepaid = $this->resolvePrepaidTenders($customer, $cart->currency, $grandTotal, $data);
            $amountDue = $prepaid['amount_due'];
            $isFullyPrepaid = bccomp($amountDue, '0', 2) <= 0;

            $session = CheckoutSession::query()->create([
                'customer_id' => $customer->id,
                'cart_id' => $cart->id,
                'idempotency_key' => $idempotencyKey,
                'status' => CheckoutSessionStatus::Processing,
                'currency' => $cart->currency,
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $billingAddress?->id,
                'shipping_method_id' => $shippingMethod?->id,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $amountDue,
            ]);

            $orderAttributes = [
                'customer_id' => $customer->id,
                'currency' => $cart->currency,
                'status' => $isFullyPrepaid ? OrderStatus::Confirmed : OrderStatus::Pending,
                'payment_status' => $isFullyPrepaid ? OrderPaymentStatus::Paid : OrderPaymentStatus::Unpaid,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'tax_snapshot' => $taxResult['snapshot'],
                'shipping_total' => $shippingTotal,
                'grand_total' => $amountDue,
                'shipping_method_id' => $shippingMethod?->id,
                'shipping_address_snapshot' => $this->addressSnapshot($shippingAddress),
                'billing_address_snapshot' => $billingAddress !== null
                    ? $this->addressSnapshot($billingAddress)
                    : $this->addressSnapshot($shippingAddress),
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'placed_at' => now(),
                'confirmed_at' => $isFullyPrepaid ? now() : null,
            ];

            if (Schema::hasColumn('orders', 'coupon_id')) {
                $orderAttributes['coupon_id'] = $discountApplication->couponId;
                $orderAttributes['coupon_code'] = $discountApplication->couponCode;
                $orderAttributes['promotion_snapshot'] = $discountApplication->promotionSnapshot !== []
                    ? $discountApplication->promotionSnapshot
                    : null;
            }

            if (Schema::hasColumn('orders', 'loyalty_points_redeemed')) {
                $orderAttributes['loyalty_points_redeemed'] = $discountApplication->loyaltyPointsRedeemed > 0
                    ? $discountApplication->loyaltyPointsRedeemed
                    : null;
            }

            if (Schema::hasColumn('orders', 'gift_card_id')) {
                $orderAttributes['gift_card_id'] = $prepaid['gift_card']?->id;
                $orderAttributes['gift_card_amount'] = bccomp($prepaid['gift_card_amount'], '0', 2) > 0
                    ? $prepaid['gift_card_amount']
                    : null;
            }

            if (Schema::hasColumn('orders', 'store_credit_amount')) {
                $orderAttributes['store_credit_amount'] = bccomp($prepaid['store_credit_amount'], '0', 2) > 0
                    ? $prepaid['store_credit_amount']
                    : null;
            }

            $order = $this->createOrderWithUniqueNumber($orderAttributes);

            $this->applyPrepaidTenders($customer, $order, $prepaid);

            $flashSaleMetadata = $this->flashSaleService->consumeForCheckout($customer, $cart);

            foreach ($cart->items as $item) {
                $this->createOrderItem(
                    $order,
                    $item,
                    $lineTaxMap->get($item->id),
                    (string) ($lineDiscountMap->get($item->id) ?? '0.00'),
                    $flashSaleMetadata[(int) $item->id] ?? null,
                );
            }

            $this->discountService->recordCouponUsage($order, $discountApplication);
            $this->discountService->recordLoyaltyRedemption($order, $discountApplication);

            $this->orderInventory->reserveForOrder($order->fresh(['items']) ?? $order);

            if ($isFullyPrepaid) {
                $this->orderInventory->commitSaleForOrder($order->fresh(['items']) ?? $order);

                if ($this->commerceSettings->isMarketplaceEnabled()) {
                    $this->commissions->createForOrder($order->fresh(['items']) ?? $order);
                }

                $this->accounting->postSale($order->fresh(['items']) ?? $order);
            }

            if ($this->commerceSettings->isMarketplaceEnabled()) {
                $this->sellerOrders->splitFromOrder($order->fresh(['items.sellerOffer']) ?? $order);
            }

            $cart->status = CartStatus::Converted;
            $cart->save();

            $session->status = CheckoutSessionStatus::Completed;
            $session->order_id = $order->id;
            $session->save();

            $order = $order->fresh(['items', 'customer', 'shippingMethod']) ?? $order;

            event(new OrderCreated($order));

            if ($isFullyPrepaid) {
                event(new OrderPaid($order));
            }

            return $order;
        });
    }

    /**
     * Work out how much of the order total prepaid tenders can cover.
     *
     * The gift card is drawn down before store credit, and neither can exceed the
     * amount still owed, so the returned `amount_due` is never negative.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     gift_card: GiftCard|null,
     *     gift_card_amount: string,
     *     store_credit_amount: string,
     *     amount_due: string
     * }
     *
     * @throws ValidationException
     */
    protected function resolvePrepaidTenders(Customer $customer, string $currency, string $grandTotal, array $data): array
    {
        $amountDue = Money::add($grandTotal, '0');
        $giftCard = null;
        $giftCardAmount = '0.00';
        $storeCreditAmount = '0.00';

        $giftCardCode = isset($data['gift_card_code']) && is_string($data['gift_card_code'])
            ? trim($data['gift_card_code'])
            : '';

        if ($giftCardCode !== '' && Schema::hasTable('gift_cards')) {
            $this->assertFeatureEnabled('gift-cards', 'gift_card_code');
            $giftCard = $this->giftCardService->resolveRedeemable($giftCardCode, $currency);
            $giftCardAmount = $this->giftCardService->applicableAmount($giftCard, $amountDue);
            $amountDue = Money::sub($amountDue, $giftCardAmount);
        }

        $requestedStoreCredit = isset($data['store_credit_amount']) && $data['store_credit_amount'] !== null
            ? Money::add((string) $data['store_credit_amount'], '0')
            : '0.00';

        if (bccomp($requestedStoreCredit, '0', 2) > 0 && Schema::hasTable('store_credit_accounts')) {
            $this->assertFeatureEnabled('store-credit', 'store_credit_amount');
            $storeCreditAmount = $this->storeCreditService->applicableAmount($customer, $requestedStoreCredit, $amountDue);
            $amountDue = Money::sub($amountDue, $storeCreditAmount);
        }

        return [
            'gift_card' => $giftCard,
            'gift_card_amount' => $giftCardAmount,
            'store_credit_amount' => $storeCreditAmount,
            'amount_due' => $amountDue,
        ];
    }

    /**
     * Draw down the resolved prepaid tenders against a freshly created order.
     *
     * @param  array{
     *     gift_card: GiftCard|null,
     *     gift_card_amount: string,
     *     store_credit_amount: string,
     *     amount_due: string
     * }  $prepaid
     *
     * @throws ValidationException
     */
    protected function applyPrepaidTenders(Customer $customer, Order $order, array $prepaid): void
    {
        if ($prepaid['gift_card'] !== null && bccomp($prepaid['gift_card_amount'], '0', 2) > 0) {
            $this->giftCardService->redeem($prepaid['gift_card'], $prepaid['gift_card_amount'], $order);
        }

        if (bccomp($prepaid['store_credit_amount'], '0', 2) > 0) {
            $this->storeCreditService->debit(
                $customer,
                $prepaid['store_credit_amount'],
                'Applied to order '.$order->order_number,
                $order,
            );
        }
    }

    protected function findIdempotentOrder(Customer $customer, string $key): ?Order
    {
        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->where('idempotency_key', $key)
            ->first();

        if ($order !== null) {
            return $order;
        }

        $session = CheckoutSession::query()
            ->where('customer_id', $customer->id)
            ->where('idempotency_key', $key)
            ->where('status', CheckoutSessionStatus::Completed)
            ->whereNotNull('order_id')
            ->first();

        return $session?->order;
    }

    /**
     * @throws ValidationException
     */
    protected function resolveCustomerAddress(Customer $customer, int $addressId, string $field): CustomerAddress
    {
        $address = CustomerAddress::query()
            ->whereKey($addressId)
            ->where('customer_id', $customer->id)
            ->first();

        if ($address === null) {
            throw ValidationException::withMessages([
                $field => 'The selected address does not belong to this customer.',
            ]);
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    protected function addressSnapshot(CustomerAddress $address): array
    {
        return [
            'id' => $address->id,
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'phone' => $address->phone,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'country_id' => $address->country_id,
            'state_id' => $address->state_id,
            'city_id' => $address->city_id,
            'postal_code' => $address->postal_code,
            'landmark' => $address->landmark,
        ];
    }

    /**
     * Persist an order, regenerating the order number when a unique collision occurs.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createOrderWithUniqueNumber(array $attributes): Order
    {
        $attempts = 0;

        while ($attempts < 8) {
            $attempts++;
            $attributes['order_number'] = $this->generateOrderNumber();

            try {
                return Order::query()->create($attributes);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempts >= 8) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'order' => 'Unable to allocate a unique order number. Please try again.',
        ]);
    }

    protected function generateOrderNumber(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd').'-';

        $latest = Order::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->lockForUpdate()
            ->value('order_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/(\d{6})$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create an order item snapshot and attach inventory_id when stock can be reserved.
     *
     * @param  array{flash_sale_item_id: int, flash_sale_id: int, sale_price: string}|null  $flashSaleMeta
     *
     * @throws ValidationException
     */
    protected function createOrderItem(
        Order $order,
        CartItem $item,
        ?array $lineTax = null,
        string $discountAmount = '0.00',
        ?array $flashSaleMeta = null,
    ): void {
        /** @var Product $product */
        $product = $item->product;
        $variant = $item->productVariant;
        $offer = $item->sellerOffer;

        $sku = $offer?->sku ?? $variant?->sku;
        $name = $variant?->name ? $product->name.' — '.$variant->name : $product->name;
        $unitPrice = (string) $item->unit_price;
        $subtotal = (string) $item->subtotal;
        $discountAmount = Money::add($discountAmount, '0');
        $taxAmount = is_array($lineTax) ? (string) ($lineTax['tax_amount'] ?? '0.00') : '0.00';
        $total = Money::add(Money::sub($subtotal, $discountAmount), $taxAmount);

        $inventory = $offer !== null
            ? $this->findOfferInventoryForReservation($offer, $item->quantity)
            : $this->findInventoryForReservation($product, $variant, $item->quantity);

        $metadata = is_array($lineTax) ? ['tax_breakdown' => $lineTax['breakdown'] ?? []] : [];

        if ($flashSaleMeta !== null) {
            $metadata = array_merge($metadata, $flashSaleMeta);
        }

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'seller_offer_id' => $offer?->id,
            'seller_id' => $offer?->seller_id,
            'product_name' => $name,
            'sku' => $sku,
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'total' => $total,
            'metadata' => $metadata !== [] ? $metadata : null,
            'inventory_id' => $inventory?->id,
        ]);
    }

    /**
     * Prefer default warehouse inventory with enough available qty for a seller offer.
     */
    protected function findOfferInventoryForReservation(SellerOffer $offer, int $quantity): ?Inventory
    {
        $inventories = Inventory::query()
            ->with('warehouse')
            ->where('inventoryable_type', $offer->getMorphClass())
            ->where('inventoryable_id', $offer->getKey())
            ->lockForUpdate()
            ->get();

        $withStock = $inventories
            ->filter(fn (Inventory $inventory): bool => $inventory->availableQuantity() >= $quantity)
            ->values();

        $preferred = $withStock->first(
            fn (Inventory $inventory): bool => (bool) $inventory->warehouse?->is_default
        ) ?? $withStock->first();

        if ($preferred !== null) {
            return $preferred;
        }

        throw ValidationException::withMessages([
            'cart' => 'Insufficient seller offer stock to reserve for one or more cart items.',
        ]);
    }

    /**
     * Prefer default warehouse inventory with enough available qty.
     * Backorders with zero stock skip reservation (null inventory_id).
     */
    protected function findInventoryForReservation(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
    ): ?Inventory {
        $stockable = $variant ?? $product;
        $allowBackorder = $variant !== null
            ? (bool) ($variant->allow_backorder ?? $product->allow_backorder)
            : (bool) $product->allow_backorder;

        $inventories = Inventory::query()
            ->with('warehouse')
            ->where('inventoryable_type', $stockable->getMorphClass())
            ->where('inventoryable_id', $stockable->getKey())
            ->lockForUpdate()
            ->get();

        $withStock = $inventories
            ->filter(fn (Inventory $inventory): bool => $inventory->availableQuantity() >= $quantity)
            ->values();

        $preferred = $withStock->first(
            fn (Inventory $inventory): bool => (bool) $inventory->warehouse?->is_default
        ) ?? $withStock->first();

        if ($preferred !== null) {
            return $preferred;
        }

        if ($allowBackorder) {
            return null;
        }

        throw ValidationException::withMessages([
            'cart' => 'Insufficient stock to reserve for one or more cart items.',
        ]);
    }

    /**
     * Enforce plan feature access when the tenant has an active/trialing subscription.
     *
     * @throws ValidationException
     */
    protected function assertFeatureEnabled(string $featureSlug, string $field): void
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        if ($tenant->activeSubscription() === null) {
            return;
        }

        if (! $this->featureGate->allows($featureSlug, $tenant)) {
            throw ValidationException::withMessages([
                $field => "Your current plan does not include the [{$featureSlug}] feature.",
            ]);
        }
    }
}
