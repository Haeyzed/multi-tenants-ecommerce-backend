<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\CartStatus;
use App\Enums\Tenant\Commerce\CheckoutSessionStatus;
use App\Enums\Tenant\Commerce\FulfillmentStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Events\OrderCreated;
use App\Models\Tenant\Cart;
use App\Models\Tenant\CartItem;
use App\Models\Tenant\CheckoutSession;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\ShippingMethod;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts an active cart into a sales order.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CommerceSettingService $commerceSettings,
        private readonly OrderInventoryService $orderInventory,
    ) {}

    /**
     * Checkout the customer's active cart.
     *
     * @param  array{
     *     shipping_address_id: int,
     *     billing_address_id?: int|null,
     *     shipping_method_id?: int|null,
     *     idempotency_key?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function checkout(Customer $customer, array $data): Order
    {
        return DB::transaction(function () use ($customer, $data): Order {
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

            $cart->load(['items.product', 'items.productVariant']);

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Cart is empty.',
                ]);
            }

            foreach ($cart->items as $item) {
                $this->cartService->revalidateItem($item, $cart->currency);
            }

            $cart->refresh()->load(['items.product', 'items.productVariant']);

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
            $discountTotal = '0.00';
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

            $taxTotal = Money::percent($subtotal, $this->commerceSettings->taxRate());
            $grandTotal = Money::add(
                Money::add(Money::sub($subtotal, $discountTotal), $taxTotal),
                $shippingTotal,
            );

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
                'grand_total' => $grandTotal,
            ]);

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'currency' => $cart->currency,
                'status' => OrderStatus::Pending,
                'payment_status' => OrderPaymentStatus::Unpaid,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $grandTotal,
                'shipping_method_id' => $shippingMethod?->id,
                'shipping_address_snapshot' => $this->addressSnapshot($shippingAddress),
                'billing_address_snapshot' => $billingAddress !== null
                    ? $this->addressSnapshot($billingAddress)
                    : $this->addressSnapshot($shippingAddress),
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $this->createOrderItem($order, $item);
            }

            $this->orderInventory->reserveForOrder($order->fresh(['items']) ?? $order);

            $cart->status = CartStatus::Converted;
            $cart->save();

            $session->status = CheckoutSessionStatus::Completed;
            $session->order_id = $order->id;
            $session->save();

            $order = $order->fresh(['items', 'customer', 'shippingMethod']) ?? $order;

            event(new OrderCreated($order));

            return $order;
        });
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

    protected function generateOrderNumber(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd').'-';

        $latest = Order::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
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
     * @throws ValidationException
     */
    protected function createOrderItem(Order $order, CartItem $item): void
    {
        /** @var Product $product */
        $product = $item->product;
        $variant = $item->productVariant;

        $sku = $variant?->sku;
        $name = $variant?->name ? $product->name.' — '.$variant->name : $product->name;
        $unitPrice = (string) $item->unit_price;
        $subtotal = (string) $item->subtotal;

        $inventory = $this->findInventoryForReservation($product, $variant, $item->quantity);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $name,
            'sku' => $sku,
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'inventory_id' => $inventory?->id,
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
}
