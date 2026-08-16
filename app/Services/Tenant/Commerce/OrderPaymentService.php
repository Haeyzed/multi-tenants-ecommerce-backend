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
use App\Models\Tenant\PaymentWebhookEvent;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentWebhookManager;
use App\Services\Tenant\Accounting\AccountingService;
use App\Services\Tenant\Marketplace\CommissionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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

        /** @var OrderPayment $payment */
        $payment = DB::transaction(function () use ($order, $customer): OrderPayment {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === OrderPaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'order' => 'Order is already paid.',
                ]);
            }

            if (bccomp((string) $locked->grand_total, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'Order has no remaining balance to charge.',
                ]);
            }

            $existing = OrderPayment::query()
                ->where('order_id', $locked->id)
                ->where('status', OrderPaymentRecordStatus::Pending)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $reference = 'ORDPAY-'.$locked->order_number.'-'.uniqid();
            $gateway = (string) config('payment.default', 'paystack');

            $created = OrderPayment::query()->create([
                'order_id' => $locked->id,
                'customer_id' => $customer->id,
                'amount' => $locked->grand_total,
                'currency' => $locked->currency,
                'gateway' => $gateway,
                'reference' => $reference,
                'status' => OrderPaymentRecordStatus::Pending,
                'metadata' => [
                    'order_id' => $locked->id,
                    'order_number' => $locked->order_number,
                ],
            ]);

            $locked->payment_status = OrderPaymentStatus::Pending;
            $locked->save();

            return $created;
        });

        $initiation = $this->paymentManager->driver($payment->gateway)->initializePayment(
            new PaymentInitiationRequest(
                amount: (string) $payment->amount,
                currency: $payment->currency,
                email: $customer->email,
                reference: $payment->reference,
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

            if ($order->payment_status === OrderPaymentStatus::Paid) {
                $locked->status = OrderPaymentRecordStatus::Cancelled;
                $locked->provider_transaction_id = $providerTransactionId ?? $locked->provider_transaction_id;
                $locked->failed_at = now();
                $locked->save();

                Log::warning('Duplicate payment cancelled for an already-paid order; skipping sale side effects.', [
                    'order_id' => $order->id,
                    'payment_id' => $locked->id,
                    'reference' => $locked->reference,
                ]);

                return $locked->fresh(['order']) ?? $locked;
            }

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
     * Handle a verified provider webhook payload (signature already checked).
     *
     * Never marks successful without a successful gateway verify + amount/currency match.
     *
     * @param  array<string, mixed>  $payload
     * @return array{processed: bool, duplicate?: bool, payment?: OrderPayment|null}
     */
    public function handleVerifiedWebhook(
        string $provider,
        string $reference,
        array $payload,
        ?string $eventId = null,
        ?string $eventType = null,
        ?string $rawBody = null,
    ): array {
        $resolvedEventId = $eventId ?? $this->resolveWebhookEventId($payload, $rawBody, $reference);

        if (! $this->claimWebhookEvent($provider, $resolvedEventId, $eventType, $reference, $payload)) {
            $payment = OrderPayment::query()->where('reference', $reference)->first();

            return [
                'processed' => false,
                'duplicate' => true,
                'payment' => $payment,
            ];
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
     * @deprecated Prefer PaymentWebhookManager + handleVerifiedWebhook.
     *
     * @param  array<string, mixed>  $payload
     * @return array{processed: bool, duplicate?: bool, payment?: OrderPayment|null}
     */
    public function handleWebhook(array $payload, ?string $signature, ?string $rawBody = null): array
    {
        $raw = $rawBody ?? json_encode($payload, JSON_THROW_ON_ERROR);
        $request = Request::create('/payments/webhooks/paystack', 'POST', $payload, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => (string) ($signature ?? ''),
        ], content: $raw);

        /** @var PaymentWebhookManager $manager */
        $manager = app(PaymentWebhookManager::class);

        return $manager->handle('paystack', $request);
    }

    /**
     * Claim a webhook event id before side effects. Returns false when already claimed.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function claimWebhookEvent(
        string $provider,
        string $eventId,
        ?string $eventType,
        string $reference,
        array $payload,
    ): bool {
        if (! Schema::hasTable('payment_webhook_events')) {
            return true;
        }

        try {
            PaymentWebhookEvent::query()->create([
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'reference' => $reference,
                'payload' => $payload,
                'processed_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Resolve a stable provider event id for idempotency.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveWebhookEventId(array $payload, ?string $rawBody, string $reference): string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (isset($data['id'])) {
            return (string) $data['id'];
        }

        if (isset($payload['eventData']) && is_array($payload['eventData']) && isset($payload['eventData']['transactionReference'])) {
            return (string) $payload['eventData']['transactionReference'];
        }

        if (isset($payload['event'])) {
            return (string) $payload['event'].':'.$reference;
        }

        if (isset($payload['eventType'])) {
            return (string) $payload['eventType'].':'.$reference;
        }

        return hash('sha256', $rawBody ?? json_encode($payload, JSON_THROW_ON_ERROR));
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
}
