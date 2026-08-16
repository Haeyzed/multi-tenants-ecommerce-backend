<?php

declare(strict_types=1);

namespace App\Services\Tenant\Pos;

use App\DTO\Payment\PaymentInitiationRequest;
use App\Enums\Tenant\Commerce\FulfillmentStatus;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Enums\Tenant\Pos\PosCashMovementType;
use App\Enums\Tenant\Pos\PosTerminalStatus;
use App\Enums\Tenant\Pos\SalesChannel;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\POSSaleCompleted;
use App\Events\RefundCompleted;
use App\Events\RefundInitiated;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\PosCashMovement;
use App\Models\Tenant\PosSession;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Warehouse;
use App\Services\Payment\PaymentManager;
use App\Services\Tenant\Accounting\AccountingService;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Commerce\OrderInventoryService;
use App\Services\Tenant\Commerce\RefundService;
use App\Services\Tenant\Tax\TaxService;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create POS sales against an open session, with split payments and inventory commit.
 */
class PosSaleService
{
    private const array OFFLINE_METHODS = ['cash', 'card', 'bank_transfer', 'offline_card', 'offline_bank'];

    public function __construct(
        private readonly AccountingService $accounting,
        private readonly CartService $cartService,
        private readonly CommerceSettingService $commerceSettings,
        private readonly OrderInventoryService $orderInventory,
        private readonly PaymentManager $paymentManager,
        private readonly RefundService $refundService,
        private readonly TaxService $taxService,
    ) {}

    /**
     * @param  array{
     *     customer_id?: int|null,
     *     items: list<array{product_id: int, product_variant_id?: int|null, quantity: int}>,
     *     payments: list<array{method: string, amount: string|float, gateway?: string|null}>,
     *     discount_percent?: string|float|null,
     *     discount_fixed?: string|float|null,
     *     notes?: string|null,
     *     idempotency_key?: string|null,
     *     currency?: string|null
     * }  $data
     */
    public function createSale(PosSession $session, array $data): Order
    {
        $session->loadMissing(['terminal.warehouse']);

        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => 'POS session must be open to create a sale.',
            ]);
        }

        /** @var PosTerminal $terminal */
        $terminal = $session->terminal;

        if ($terminal->status !== PosTerminalStatus::Active) {
            throw ValidationException::withMessages([
                'terminal' => 'POS terminal is not active.',
            ]);
        }

        $warehouseId = $terminal->warehouse_id;
        if ($warehouseId === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Terminal must be assigned to a warehouse before selling.',
            ]);
        }

        $warehouse = Warehouse::query()->find($warehouseId);
        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Terminal warehouse was not found.',
            ]);
        }

        $idempotencyKey = isset($data['idempotency_key']) && is_string($data['idempotency_key'])
            ? trim($data['idempotency_key'])
            : null;

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Order::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('sales_channel', SalesChannel::Pos)
                ->first();

            if ($existing !== null) {
                return $existing->load(['items', 'payments', 'customer']);
            }
        }

        $currency = (string) ($data['currency'] ?? $this->commerceSettings->currencyCode());
        $customer = $this->resolveCustomer($data['customer_id'] ?? null);

        return DB::transaction(function () use ($session, $terminal, $warehouse, $data, $customer, $currency, $idempotencyKey): Order {
            $lines = $this->buildLineItems($data['items'] ?? [], $currency, $customer, (int) $warehouse->id);

            if ($lines === []) {
                throw ValidationException::withMessages([
                    'items' => 'At least one line item is required.',
                ]);
            }

            $subtotal = '0.00';
            foreach ($lines as $line) {
                $subtotal = Money::add($subtotal, $line['subtotal']);
            }

            $discountTotal = $this->resolveDiscount($subtotal, $data);
            $taxableBase = Money::sub($subtotal, $discountTotal);
            if (bccomp($taxableBase, '0', 2) < 0) {
                $taxableBase = '0.00';
            }

            $taxLines = [];
            foreach ($lines as $index => $line) {
                $lineShare = bccomp($subtotal, '0', 2) > 0
                    ? Money::mul($discountTotal, Money::div($line['subtotal'], $subtotal))
                    : '0.00';
                $taxableAmount = Money::sub($line['subtotal'], $lineShare);
                if (bccomp($taxableAmount, '0', 2) < 0) {
                    $taxableAmount = '0.00';
                }
                $taxLines[] = [
                    'key' => $index,
                    'amount' => $taxableAmount,
                ];
            }

            $taxResult = $this->taxService->calculateOrderTax($taxLines, '0.00', []);
            $taxTotal = $taxResult['tax_total'];
            $lineTaxMap = collect($taxResult['line_taxes'])->keyBy('key');

            $grandTotal = Money::add($taxableBase, $taxTotal);
            $payments = $this->normalizePayments($data['payments'] ?? [], $grandTotal);

            $paidTotal = '0.00';
            foreach ($payments as $payment) {
                $paidTotal = Money::add($paidTotal, $payment['amount']);
            }

            if (bccomp($paidTotal, $grandTotal, 2) !== 0) {
                throw ValidationException::withMessages([
                    'payments' => 'Payment totals must equal the sale grand total.',
                ]);
            }

            $isFullyPaid = true;
            foreach ($payments as $payment) {
                if ($payment['method'] === 'gateway') {
                    $isFullyPaid = false;
                    break;
                }
            }

            $orderAttributes = [
                'customer_id' => $customer->id,
                'sales_channel' => SalesChannel::Pos,
                'pos_terminal_id' => $terminal->id,
                'pos_session_id' => $session->id,
                'warehouse_id' => $warehouse->id,
                'currency' => $currency,
                'status' => $isFullyPaid ? OrderStatus::Confirmed : OrderStatus::Pending,
                'payment_status' => $isFullyPaid ? OrderPaymentStatus::Paid : OrderPaymentStatus::Pending,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'tax_snapshot' => $taxResult['snapshot'],
                'shipping_total' => '0.00',
                'grand_total' => $grandTotal,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey !== null && $idempotencyKey !== '' ? $idempotencyKey : null,
                'placed_at' => now(),
                'confirmed_at' => $isFullyPaid ? now() : null,
            ];

            $order = $this->createOrderWithUniqueNumber($orderAttributes);

            foreach ($lines as $index => $line) {
                $lineTax = $lineTaxMap->get($index);
                $taxAmount = is_array($lineTax) ? (string) ($lineTax['tax_amount'] ?? '0.00') : '0.00';
                $lineDiscount = bccomp($subtotal, '0', 2) > 0
                    ? Money::mul($discountTotal, Money::div($line['subtotal'], $subtotal))
                    : '0.00';
                $total = Money::add(Money::sub($line['subtotal'], $lineDiscount), $taxAmount);

                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'product_name' => $line['product_name'],
                    'sku' => $line['sku'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $line['subtotal'],
                    'total' => $total,
                    'metadata' => is_array($lineTax) ? ['tax_breakdown' => $lineTax['breakdown'] ?? []] : null,
                    'inventory_id' => $line['inventory_id'],
                ]);
            }

            $order = $order->fresh(['items']) ?? $order;
            $this->orderInventory->reserveForOrder($order);

            $cashierId = (int) $session->user_id;

            foreach ($payments as $payment) {
                $this->recordPayment($order, $customer, $session, $cashierId, $payment, $isFullyPaid);
            }

            if ($isFullyPaid) {
                $this->orderInventory->commitSaleForOrder($order->fresh(['items']) ?? $order);
                $this->accounting->postSale($order->fresh(['items']) ?? $order);
            }

            $order = $order->fresh(['items', 'payments', 'customer']) ?? $order;

            event(new OrderCreated($order));

            if ($isFullyPaid) {
                event(new OrderPaid($order));
                event(new POSSaleCompleted($order));
            }

            return $order;
        });
    }

    /**
     * Refund a POS order (gateway via RefundService; offline cash/card locally).
     *
     * @param  array{
     *     amount?: string|float|null,
     *     reason?: string|null,
     *     order_payment_id?: int|null
     * }  $data
     */
    public function refund(Order $order, array $data = []): Refund
    {
        if ($order->sales_channel !== SalesChannel::Pos) {
            throw ValidationException::withMessages([
                'order' => 'Only POS orders can be refunded through POS.',
            ]);
        }

        /** @var OrderPayment|null $payment */
        $payment = null;
        if (! empty($data['order_payment_id'])) {
            $payment = OrderPayment::query()
                ->where('order_id', $order->id)
                ->whereKey((int) $data['order_payment_id'])
                ->first();
        } else {
            $payment = OrderPayment::query()
                ->where('order_id', $order->id)
                ->where('status', OrderPaymentRecordStatus::Successful)
                ->latest('id')
                ->first();
        }

        if ($payment === null) {
            throw ValidationException::withMessages([
                'order' => 'No successful payment found to refund.',
            ]);
        }

        $hasProviderId = $payment->provider_transaction_id !== null && $payment->provider_transaction_id !== '';
        $isOffline = in_array(strtolower((string) $payment->gateway), self::OFFLINE_METHODS, true)
            || ! $hasProviderId;

        if (! $isOffline && $hasProviderId) {
            return $this->refundService->create($order, $payment, $data);
        }

        return $this->refundOffline($order, $payment, $data);
    }

    /**
     * Ensure the tenant walk-in customer exists.
     */
    public function ensureWalkInCustomer(): Customer
    {
        $tenantKey = (string) (tenant('id') ?? tenant('slug') ?? 'tenant');
        $email = 'walk-in@'.$tenantKey.'.pos.local';

        $existing = Customer::query()->where('email', $email)->first();
        if ($existing !== null) {
            return $existing;
        }

        $customer = Customer::query()->create([
            'first_name' => 'Walk-in',
            'last_name' => 'Customer',
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    /**
     * @param  array{amount?: string|float|null, reason?: string|null}  $data
     */
    protected function refundOffline(Order $order, OrderPayment $payment, array $data): Refund
    {
        return DB::transaction(function () use ($order, $payment, $data): Refund {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            /** @var OrderPayment $lockedPayment */
            $lockedPayment = OrderPayment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $requested = isset($data['amount']) ? Money::add((string) $data['amount'], '0') : Money::add((string) $lockedPayment->amount, '0');

            if (bccomp($requested, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount must be greater than zero.',
                ]);
            }

            if (bccomp($requested, (string) $lockedPayment->amount, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds the payment amount.',
                ]);
            }

            $refund = Refund::query()->create([
                'order_id' => $lockedOrder->id,
                'order_payment_id' => $lockedPayment->id,
                'amount' => $requested,
                'currency' => $lockedPayment->currency,
                'reference' => 'REF-POS-'.$lockedOrder->order_number.'-'.uniqid(),
                'status' => RefundStatus::Completed,
                'reason' => $data['reason'] ?? null,
                'processed_at' => now(),
                'metadata' => [
                    'type' => 'pos_offline',
                    'gateway' => $lockedPayment->gateway,
                ],
            ]);

            event(new RefundInitiated($refund));

            if (in_array(strtolower((string) $lockedPayment->gateway), ['cash'], true)
                && $lockedOrder->pos_session_id !== null
            ) {
                $sessionUserId = PosSession::query()->whereKey($lockedOrder->pos_session_id)->value('user_id');
                if ($sessionUserId !== null) {
                    PosCashMovement::query()->create([
                        'pos_session_id' => $lockedOrder->pos_session_id,
                        'type' => PosCashMovementType::RefundCash,
                        'amount' => $requested,
                        'reason' => 'POS refund '.$lockedOrder->order_number,
                        'user_id' => (int) $sessionUserId,
                    ]);
                }
            }

            $isFull = bccomp($requested, (string) $lockedOrder->grand_total, 2) >= 0;
            if ($isFull) {
                $this->accounting->postRefund($lockedOrder);
                $lockedOrder->payment_status = OrderPaymentStatus::Refunded;
                $lockedOrder->status = OrderStatus::Refunded;
            } else {
                $this->accounting->postPartialRefund($lockedOrder, $requested, $refund);
                $lockedOrder->payment_status = OrderPaymentStatus::PartiallyRefunded;
            }
            $lockedOrder->save();

            $refund = $refund->fresh(['order', 'payment']) ?? $refund;
            event(new RefundCompleted($refund));

            return $refund;
        });
    }

    protected function resolveCustomer(?int $customerId): Customer
    {
        if ($customerId !== null) {
            $customer = Customer::query()->find($customerId);
            if ($customer === null) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Customer not found.',
                ]);
            }

            return $customer;
        }

        return $this->ensureWalkInCustomer();
    }

    /**
     * @param  list<array{product_id: int, product_variant_id?: int|null, quantity: int}>  $items
     * @return list<array{
     *     product_id: int,
     *     product_variant_id: int|null,
     *     product_name: string,
     *     sku: string|null,
     *     quantity: int,
     *     unit_price: string,
     *     subtotal: string,
     *     inventory_id: int|null
     * }>
     */
    protected function buildLineItems(array $items, string $currency, Customer $customer, int $warehouseId): array
    {
        $lines = [];

        foreach ($items as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}" => 'Each item requires a product_id and positive quantity.',
                ]);
            }

            $product = Product::query()->find($productId);
            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Product not found.',
                ]);
            }

            $variant = null;
            if ($variantId !== null) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->whereKey($variantId)
                    ->first();

                if ($variant === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_variant_id" => 'Variant not found for this product.',
                    ]);
                }
            }

            $unitPrice = $this->cartService->resolveUnitPrice($product, $variant, $currency, $customer);
            $subtotal = Money::mul($unitPrice, (string) $quantity);
            $name = $variant?->name ? $product->name.' — '.$variant->name : $product->name;
            $sku = $variant?->sku;
            $inventory = $this->findInventoryForWarehouse($product, $variant, $quantity, $warehouseId);

            $lines[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $name,
                'sku' => $sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'inventory_id' => $inventory?->id,
            ];
        }

        return $lines;
    }

    protected function findInventoryForWarehouse(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        int $warehouseId,
    ): ?Inventory {
        $stockable = $variant ?? $product;
        $allowBackorder = $variant !== null
            ? (bool) ($variant->allow_backorder ?? $product->allow_backorder)
            : (bool) $product->allow_backorder;

        /** @var Inventory|null $inventory */
        $inventory = Inventory::query()
            ->where('inventoryable_type', $stockable->getMorphClass())
            ->where('inventoryable_id', $stockable->getKey())
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($inventory !== null && $inventory->availableQuantity() >= $quantity) {
            return $inventory;
        }

        if ($allowBackorder) {
            return $inventory;
        }

        throw ValidationException::withMessages([
            'items' => 'Insufficient stock in the terminal warehouse for one or more items.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveDiscount(string $subtotal, array $data): string
    {
        $discount = '0.00';

        if (isset($data['discount_percent']) && $data['discount_percent'] !== null && $data['discount_percent'] !== '') {
            $percent = Money::add((string) $data['discount_percent'], '0');
            if (bccomp($percent, '0', 2) < 0 || bccomp($percent, '100', 2) > 0) {
                throw ValidationException::withMessages([
                    'discount_percent' => 'Discount percent must be between 0 and 100.',
                ]);
            }
            $discount = Money::add($discount, Money::percent($subtotal, $percent));
        }

        if (isset($data['discount_fixed']) && $data['discount_fixed'] !== null && $data['discount_fixed'] !== '') {
            $fixed = Money::add((string) $data['discount_fixed'], '0');
            if (bccomp($fixed, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'discount_fixed' => 'Discount fixed cannot be negative.',
                ]);
            }
            $discount = Money::add($discount, $fixed);
        }

        if (bccomp($discount, $subtotal, 2) > 0) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot exceed the subtotal.',
            ]);
        }

        return $discount;
    }

    /**
     * @param  list<array{method: string, amount: string|float, gateway?: string|null}>  $payments
     * @return list<array{method: string, amount: string, gateway: string}>
     */
    protected function normalizePayments(array $payments, string $grandTotal): array
    {
        if ($payments === []) {
            throw ValidationException::withMessages([
                'payments' => 'At least one payment is required.',
            ]);
        }

        $normalized = [];

        foreach ($payments as $index => $payment) {
            $method = strtolower(trim((string) ($payment['method'] ?? '')));
            $amount = Money::add((string) ($payment['amount'] ?? '0'), '0');

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    "payments.{$index}.amount" => 'Payment amount must be greater than zero.',
                ]);
            }

            $gateway = match ($method) {
                'cash' => 'cash',
                'card', 'offline_card' => 'offline_card',
                'bank_transfer', 'offline_bank' => 'bank_transfer',
                'gateway' => strtolower(trim((string) ($payment['gateway'] ?? 'paystack'))),
                default => throw ValidationException::withMessages([
                    "payments.{$index}.method" => 'Unsupported payment method.',
                ]),
            };

            $normalized[] = [
                'method' => $method === 'gateway' ? 'gateway' : $gateway,
                'amount' => $amount,
                'gateway' => $gateway,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array{method: string, amount: string, gateway: string}  $payment
     */
    protected function recordPayment(
        Order $order,
        Customer $customer,
        PosSession $session,
        int $cashierId,
        array $payment,
        bool $markSuccessful,
    ): OrderPayment {
        $reference = 'POS-'.$order->order_number.'-'.Str::upper(Str::random(8));

        $record = OrderPayment::query()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'amount' => $payment['amount'],
            'currency' => $order->currency,
            'gateway' => $payment['gateway'],
            'reference' => $reference,
            'status' => $markSuccessful && $payment['method'] !== 'gateway'
                ? OrderPaymentRecordStatus::Successful
                : OrderPaymentRecordStatus::Pending,
            'paid_at' => $markSuccessful && $payment['method'] !== 'gateway' ? now() : null,
            'metadata' => [
                'pos_session_id' => $session->id,
                'pos_terminal_id' => $order->pos_terminal_id,
                'method' => $payment['method'],
            ],
        ]);

        if ($payment['gateway'] === 'cash' && $markSuccessful) {
            PosCashMovement::query()->create([
                'pos_session_id' => $session->id,
                'type' => PosCashMovementType::SaleCash,
                'amount' => $payment['amount'],
                'reason' => 'Sale '.$order->order_number,
                'user_id' => $cashierId,
            ]);
        }

        if ($payment['method'] === 'gateway') {
            try {
                $initiation = $this->paymentManager->driver($payment['gateway'])->initializePayment(
                    new PaymentInitiationRequest(
                        amount: $payment['amount'],
                        currency: $order->currency,
                        email: $customer->email,
                        reference: $reference,
                        metadata: [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'customer_id' => $customer->id,
                            'pos' => true,
                        ],
                        customerName: trim($customer->first_name.' '.$customer->last_name),
                    ),
                );

                $record->metadata = array_merge($record->metadata ?? [], [
                    'initiation' => [
                        'authorization_url' => $initiation->authorizationUrl,
                        'access_code' => $initiation->accessCode,
                    ],
                ]);
                $record->save();
            } catch (\Throwable) {
                // Leave payment pending when gateway init fails; cashier can retry via payments API.
            }
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createOrderWithUniqueNumber(array $attributes): Order
    {
        $attempts = 0;

        while ($attempts < 8) {
            $attempts++;
            $attributes['order_number'] = 'POS-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

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
}
