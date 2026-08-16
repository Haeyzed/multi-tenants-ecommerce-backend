<?php

declare(strict_types=1);

namespace App\Services\Payment\Webhooks;

use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\PaymentTransactionStatus;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\WebhookEvent;
use App\Services\Landlord\Subscription\SubscriptionService;
use App\Services\Payment\PaymentManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Validates and processes Paystack webhook events idempotently.
 */
class PaystackWebhookHandler
{
    /**
     * Create a new Paystack webhook handler.
     */
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly PaymentManager $paymentManager,
    ) {}

    /**
     * Handle an inbound Paystack webhook request.
     *
     * @return array{processed: bool, duplicate?: bool, event_type?: string|null}
     */
    public function handle(Request $request): array
    {
        $this->assertValidSignature($request);

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $eventType = isset($payload['event']) ? (string) $payload['event'] : null;
        $eventId = $this->resolveEventId($payload, $request);

        if (! $this->claimWebhookEvent($eventId, $eventType, $payload)) {
            return [
                'processed' => false,
                'duplicate' => true,
                'event_type' => $eventType,
            ];
        }

        if ($eventType === 'charge.success') {
            $this->handleChargeSuccess($payload);
        }

        return [
            'processed' => true,
            'duplicate' => false,
            'event_type' => $eventType,
        ];
    }

    /**
     * Claim a webhook event id before side effects. Returns false when already claimed.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function claimWebhookEvent(string $eventId, ?string $eventType, array $payload): bool
    {
        try {
            DB::connection((string) config('tenancy.database.central_connection'))
                ->transaction(function () use ($eventId, $eventType, $payload): void {
                    WebhookEvent::query()->create([
                        'provider' => PaymentProvider::Paystack,
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                        'payload' => $payload,
                        'processed_at' => now(),
                    ]);
                });

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Activate the related subscription after server-side payment verification.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeSuccess(array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = isset($data['reference']) ? (string) $data['reference'] : null;

        if ($reference === null || $reference === '') {
            Log::warning('Paystack charge.success webhook missing reference.');

            return;
        }

        /** @var PaymentTransaction|null $transaction */
        $transaction = PaymentTransaction::query()
            ->where('reference', $reference)
            ->where('provider', PaymentProvider::Paystack)
            ->first();

        if ($transaction === null) {
            Log::warning('Paystack charge.success webhook for unknown reference.', ['reference' => $reference]);

            return;
        }

        if ($transaction->status === PaymentTransactionStatus::Successful) {
            return;
        }

        $result = $this->paymentManager->driver($transaction->provider->value)->verifyPayment($reference);

        if (! $result->successful) {
            Log::warning('Paystack charge.success webhook failed gateway verification.', [
                'reference' => $reference,
                'message' => $result->message,
            ]);

            return;
        }

        if ($result->amount === null || $result->currency === null) {
            Log::warning('Paystack charge.success webhook missing verified amount or currency.', [
                'reference' => $reference,
            ]);

            return;
        }

        if (bccomp($result->amount, (string) $transaction->amount, 2) !== 0) {
            Log::warning('Paystack charge.success webhook amount mismatch.', [
                'reference' => $reference,
                'expected' => $transaction->amount,
                'verified' => $result->amount,
            ]);

            return;
        }

        if (strtoupper($result->currency) !== strtoupper((string) $transaction->currency)) {
            Log::warning('Paystack charge.success webhook currency mismatch.', [
                'reference' => $reference,
                'expected' => $transaction->currency,
                'verified' => $result->currency,
            ]);

            return;
        }

        $this->subscriptionService->activateFromVerifiedPayment(
            $transaction,
            $result->providerTransactionId ?? (isset($data['id']) ? (string) $data['id'] : null),
            $result->paidAt ?? now(),
        );
    }

    /**
     * Validate the Paystack webhook signature header.
     */
    protected function assertValidSignature(Request $request): void
    {
        $secret = (string) config('payment.drivers.paystack.webhook_secret');

        if ($secret === '') {
            throw new RuntimeException('Paystack webhook secret is not configured.');
        }

        $signature = (string) $request->header('x-paystack-signature', '');
        $computed = hash_hmac('sha512', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($computed, $signature)) {
            throw new AccessDeniedHttpException('Invalid Paystack webhook signature.');
        }
    }

    /**
     * Resolve a stable event identifier for idempotency.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveEventId(array $payload, Request $request): string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (isset($data['id'])) {
            return (string) $data['id'];
        }

        if (isset($payload['event']) && isset($data['reference'])) {
            return (string) $payload['event'].':'.$data['reference'];
        }

        return hash('sha256', $request->getContent());
    }
}
