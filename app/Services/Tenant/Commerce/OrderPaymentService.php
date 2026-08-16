<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentVerificationResult;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Events\OrderPaid;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Services\Payment\PaymentManager;
use App\Services\Tenant\Accounting\AccountingService;
use App\Services\Tenant\Marketplace\CommissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tenant order payment initiation, verification, and webhook handling.
 */
class OrderPaymentService
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly OrderInventoryService $orderInventory,
        private readonly AccountingService $accounting,
        private readonly CommissionService $commissions,
        private readonly CommerceSettingService $commerceSettings,
    ) {}

    /**
     * Initialize a gateway payment for an order.
     *
     * Reuses an existing pending payment for the same order when present.
     *
     * @return array{initiation: PaymentInitiationResult, payment: OrderPayment}
     *
     * @throws ValidationException
     */
    public function initialize(Order $order, Customer $customer): array
    {
        if ($order->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'order' => 'Order does not belong to this customer.',
            ]);
        }

        if ($order->payment_status === OrderPaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'order' => 'Order is already paid.',
            ]);
        }

        if (bccomp((string) $order->grand_total, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'order' => 'Order has no remaining balance to charge.',
            ]);
        }

        $existing = OrderPayment::query()
            ->where('order_id', $order->id)
            ->where('status', OrderPaymentRecordStatus::Pending)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            $initiation = $this->paymentManager->driver($existing->gateway)->initializePayment(
                new PaymentInitiationRequest(
                    amount: (string) $existing->amount,
                    currency: $existing->currency,
                    email: $customer->email,
                    reference: $existing->reference,
                    metadata: [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_id' => $customer->id,
                    ],
                    customerName: trim($customer->first_name.' '.$customer->last_name),
                ),
            );

            return [
                'initiation' => $initiation,
                'payment' => $existing->fresh() ?? $existing,
            ];
        }

        $order->loadMissing('customer');
        $reference = 'ORDPAY-'.$order->order_number.'-'.uniqid();
        $gateway = (string) config('payment.default', 'paystack');

        $initiation = $this->paymentManager->driver($gateway)->initializePayment(
            new PaymentInitiationRequest(
                amount: (string) $order->grand_total,
                currency: $order->currency,
                email: $customer->email,
                reference: $reference,
                metadata: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_id' => $customer->id,
                ],
                customerName: trim($customer->first_name.' '.$customer->last_name),
            ),
        );

        $payment = OrderPayment::query()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'gateway' => $gateway,
            'reference' => $reference,
            'status' => OrderPaymentRecordStatus::Pending,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);

        $order->payment_status = OrderPaymentStatus::Pending;
        $order->save();

        return [
            'initiation' => $initiation,
            'payment' => $payment->fresh() ?? $payment,
        ];
    }

    /**
     * Verify a payment reference for an authenticated customer (ownership first).
     *
     * @throws ValidationException
     * @throws AccessDeniedHttpException
     */
    public function verifyForCustomer(string $reference, Customer $customer): OrderPayment
    {
        /** @var OrderPayment $payment */
        $payment = OrderPayment::query()->where('reference', $reference)->firstOrFail();

        if ($payment->customer_id !== $customer->id) {
            throw new AccessDeniedHttpException('Payment does not belong to this customer.');
        }

        return $this->verify($reference);
    }

    /**
     * Verify a payment reference with the gateway and mark success/failure.
     *
     * @throws ValidationException
     */
    public function verify(string $reference): OrderPayment
    {
        /** @var OrderPayment $payment */
        $payment = OrderPayment::query()->where('reference', $reference)->firstOrFail();

        if ($payment->status === OrderPaymentRecordStatus::Successful) {
            return $payment->load('order');
        }

        $result = $this->paymentManager->driver($payment->gateway)->verifyPayment($reference);

        $this->assertVerificationMatchesPayment($payment, $result);

        if ($result->successful) {
            return $this->markSuccessful(
                $payment,
                $result->providerTransactionId,
                $result->paidAt?->toDateTimeString(),
            );
        }

        return $this->markFailed($payment);
    }

    /**
     * Mark payment successful: confirm order, commit stock, post sale, fire OrderPaid.
     */
    public function markSuccessful(
        OrderPayment $payment,
        ?string $providerTransactionId = null,
        ?string $paidAt = null,
    ): OrderPayment {
        return DB::transaction(function () use ($payment, $providerTransactionId, $paidAt): OrderPayment {
            /** @var OrderPayment $locked */
            $locked = OrderPayment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderPaymentRecordStatus::Successful) {
                return $locked->load('order');
            }

            /** @var Order $order */
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $locked->status = OrderPaymentRecordStatus::Successful;
            $locked->provider_transaction_id = $providerTransactionId ?? $locked->provider_transaction_id;
            $locked->paid_at = $paidAt !== null ? $paidAt : now();
            $locked->failed_at = null;
            $locked->save();

            $order->payment_status = OrderPaymentStatus::Paid;
            $order->status = OrderStatus::Confirmed;
            if ($order->confirmed_at === null) {
                $order->confirmed_at = now();
            }
            $order->save();

            $this->orderInventory->commitSaleForOrder($order);

            if ($this->commerceSettings->isMarketplaceEnabled()) {
                $this->commissions->createForOrder($order);
            }

            $this->accounting->postSale($order);

            event(new OrderPaid($order->fresh(['items', 'customer']) ?? $order));

            return $locked->fresh(['order']) ?? $locked;
        });
    }

    /**
     * Mark a payment as failed.
     */
    public function markFailed(OrderPayment $payment): OrderPayment
    {
        return DB::transaction(function () use ($payment): OrderPayment {
            /** @var OrderPayment $locked */
            $locked = OrderPayment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderPaymentRecordStatus::Successful) {
                return $locked->load('order');
            }

            $locked->status = OrderPaymentRecordStatus::Failed;
            $locked->failed_at = now();
            $locked->save();

            /** @var Order $order */
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status !== OrderPaymentStatus::Paid) {
                $order->payment_status = OrderPaymentStatus::Failed;
                $order->save();
            }

            return $locked->fresh(['order']) ?? $locked;
        });
    }

    /**
     * Handle a Paystack webhook payload.
     *
     * Never marks successful without a successful gateway verify + amount/currency match.
     *
     * @param  array<string, mixed>  $payload
     * @return array{processed: bool, payment?: OrderPayment|null}
     */
    public function handleWebhook(array $payload, ?string $signature, ?string $rawBody = null): array
    {
        $this->assertValidPaystackSignature($signature, $rawBody ?? json_encode($payload, JSON_THROW_ON_ERROR));

        $eventType = isset($payload['event']) ? (string) $payload['event'] : null;

        if ($eventType !== 'charge.success') {
            return ['processed' => false];
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = isset($data['reference']) ? (string) $data['reference'] : '';

        if ($reference === '') {
            return ['processed' => false];
        }

        $payment = OrderPayment::query()->where('reference', $reference)->first();

        if ($payment === null) {
            return ['processed' => false];
        }

        if ($payment->status === OrderPaymentRecordStatus::Successful) {
            return ['processed' => true, 'payment' => $payment];
        }

        $verified = $this->verify($reference);

        return ['processed' => true, 'payment' => $verified];
    }

    /**
     * Ensure gateway verification matches the stored payment amount and currency.
     *
     * @throws ValidationException
     */
    protected function assertVerificationMatchesPayment(OrderPayment $payment, PaymentVerificationResult $result): void
    {
        if ($result->amount === null || $result->currency === null) {
            $this->markFailed($payment);

            throw ValidationException::withMessages([
                'reference' => ['Payment verification did not return amount and currency.'],
            ]);
        }

        if (bccomp((string) $result->amount, (string) $payment->amount, 2) !== 0) {
            $this->markFailed($payment);

            throw ValidationException::withMessages([
                'reference' => ['Verified payment amount does not match the expected amount.'],
            ]);
        }

        if (strtoupper((string) $result->currency) !== strtoupper((string) $payment->currency)) {
            $this->markFailed($payment);

            throw ValidationException::withMessages([
                'reference' => ['Verified payment currency does not match the expected currency.'],
            ]);
        }
    }

    /**
     * Validate Paystack HMAC signature (same pattern as landlord webhook handler).
     */
    protected function assertValidPaystackSignature(?string $signature, string $rawBody): void
    {
        $secret = (string) config('payment.drivers.paystack.webhook_secret');

        if ($secret === '') {
            throw new RuntimeException('Paystack webhook secret is not configured.');
        }

        $computed = hash_hmac('sha512', $rawBody, $secret);

        if ($signature === null || $signature === '' || ! hash_equals($computed, $signature)) {
            throw new AccessDeniedHttpException('Invalid Paystack webhook signature.');
        }
    }
}
