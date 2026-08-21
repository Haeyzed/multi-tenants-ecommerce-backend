<?php

declare(strict_types=1);

namespace App\Services\Landlord\Subscription;

use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\Enums\Landlord\BillingInterval;
use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\PaymentTransactionStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Events\SubscriptionActivated;
use App\Events\SubscriptionCancelled;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Payment\PaymentManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Tenant subscription lifecycle and payment verification.
 */
class SubscriptionService
{
    /**
     * Create a new subscription service.
     *
     * @param  PaymentManager  $paymentManager
     */
    public function __construct(private readonly PaymentManager $paymentManager) {}

    /**
     * Run a callback inside a central-database transaction.
     *
     * @param  callable(): TReturn  $callback
     * @return mixed
     */
    protected function centralTransaction(callable $callback): mixed
    {
        return DB::connection((string) config('tenancy.database.central_connection'))
            ->transaction($callback);
    }

    /**
     * Resolve the tenant's current access-granting subscription.
     *
     * @param  Tenant  $tenant
     * @return ?Subscription
     */
    public function currentForTenant(Tenant $tenant): ?Subscription
    {
        return $tenant->activeSubscription()?->load(['plan.features']);
    }

    /**
     * Subscribe a tenant to a plan.
     *
     * @param  Tenant  $tenant
     * @param  Plan  $plan
     * @param  array{email?: string|null, callback_url?: string|null, customer_name?: string|null, metadata?: array<string, mixed>}  $options
     * @return array{subscription: Subscription, payment: PaymentInitiationResult|null}
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function subscribe(Tenant $tenant, Plan $plan, array $options = []): array
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => ['The selected plan is not active.'],
            ]);
        }

        if ($plan->isFree()) {
            $subscription = $this->activateFreePlan($tenant, $plan, $options);

            return [
                'subscription' => $subscription,
                'payment' => null,
            ];
        }

        return $this->initializePaidSubscription($tenant, $plan, $options);
    }

    /**
     * Verify a pending payment reference and activate the related subscription.
     *
     * @param  Tenant  $tenant
     * @param  string  $reference
     * @return Subscription
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function verifyPayment(Tenant $tenant, string $reference): Subscription
    {
        /** @var PaymentTransaction|null $transaction */
        $transaction = PaymentTransaction::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('reference', $reference)
            ->first();

        if ($transaction === null) {
            throw ValidationException::withMessages([
                'reference' => ['Payment transaction not found for this tenant.'],
            ]);
        }

        if ($transaction->status === PaymentTransactionStatus::Successful) {
            /** @var Subscription $subscription */
            $subscription = $transaction->subscription()->with(['plan.features'])->firstOrFail();

            return $subscription;
        }

        $gateway = $this->paymentManager->driver($transaction->provider->value);
        $result = $gateway->verifyPayment($reference);

        if (! $result->successful) {
            $transaction->update([
                'status' => PaymentTransactionStatus::Failed,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'verification' => $result->toArray(),
                ]),
            ]);

            event(new PaymentFailed($transaction->fresh() ?? $transaction, $result->message));

            throw ValidationException::withMessages([
                'reference' => [$result->message ?? 'Payment verification failed.'],
            ]);
        }

        if ($result->amount !== null && bccomp($result->amount, (string) $transaction->amount, 2) !== 0) {
            throw ValidationException::withMessages([
                'reference' => ['Verified payment amount does not match the expected amount.'],
            ]);
        }

        if ($result->currency !== null && strtoupper($result->currency) !== strtoupper((string) $transaction->currency)) {
            throw ValidationException::withMessages([
                'reference' => ['Verified payment currency does not match the expected currency.'],
            ]);
        }

        return $this->activateFromVerifiedPayment(
            $transaction,
            $result->providerTransactionId,
            $result->paidAt ?? now(),
        );
    }

    /**
     * Activate a subscription after a successful payment verification or webhook.
     *
     * @param  PaymentTransaction  $transaction
     * @param  ?string  $providerTransactionId
     * @param  ?CarbonInterface  $paidAt
     * @return Subscription
     */
    public function activateFromVerifiedPayment(
        PaymentTransaction $transaction,
        ?string $providerTransactionId = null,
        ?CarbonInterface $paidAt = null,
    ): Subscription {
        return $this->centralTransaction(function () use ($transaction, $providerTransactionId, $paidAt): Subscription {
            /** @var PaymentTransaction $transaction */
            $transaction = PaymentTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status === PaymentTransactionStatus::Successful) {
                /** @var Subscription $subscription */
                $subscription = $transaction->subscription()->with(['plan.features'])->firstOrFail();

                return $subscription;
            }

            /** @var Subscription $subscription */
            $subscription = Subscription::query()
                ->whereKey($transaction->subscription_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Plan $plan */
            $plan = $subscription->plan()->firstOrFail();

            $this->cancelConflictingSubscriptions($subscription->tenant_id, $subscription->id);

            $period = $this->periodBounds($plan, now());

            $subscription->fill([
                'status' => $plan->trial_days > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'starts_at' => $period['starts_at'],
                'ends_at' => null,
                'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'current_period_start' => $period['current_period_start'],
                'current_period_end' => $period['current_period_end'],
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'auto_renew' => true,
            ]);
            $subscription->save();

            $transaction->update([
                'status' => PaymentTransactionStatus::Successful,
                'provider_transaction_id' => $providerTransactionId ?? $transaction->provider_transaction_id,
                'paid_at' => $paidAt ?? now(),
            ]);

            $subscription = $subscription->fresh(['plan.features']) ?? $subscription->load(['plan.features']);

            event(new SubscriptionActivated($subscription));
            event(new PaymentSucceeded($transaction->fresh() ?? $transaction));

            return $subscription;
        });
    }

    /**
     * Cancel a subscription immediately or at the end of the current period.
     *
     * @param  Subscription  $subscription
     * @param  bool  $immediately
     * @return Subscription
     */
    public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    {
        if (in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
            return $subscription->load(['plan.features']);
        }

        if ($immediately) {
            $subscription->fill([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
                'ends_at' => now(),
                'cancel_at_period_end' => false,
                'auto_renew' => false,
            ]);
        } else {
            $subscription->fill([
                'cancelled_at' => now(),
                'cancel_at_period_end' => true,
                'auto_renew' => false,
            ]);
        }

        $subscription->save();

        $subscription = $subscription->fresh(['plan.features']) ?? $subscription->load(['plan.features']);

        event(new SubscriptionCancelled($subscription));

        return $subscription;
    }

    /**
     * Change the tenant's plan, cancelling the current access-granting subscription.
     *
     * @param  Tenant  $tenant
     * @param  Plan  $plan
     * @param  array{email?: string|null, callback_url?: string|null, customer_name?: string|null, metadata?: array<string, mixed>, immediate?: bool}  $options
     * @return array{subscription: Subscription, payment: PaymentInitiationResult|null}
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function changePlan(Tenant $tenant, Plan $plan, array $options = []): array
    {
        $current = $this->currentForTenant($tenant);

        if ($current !== null && (int) $current->plan_id === (int) $plan->id) {
            throw ValidationException::withMessages([
                'plan_id' => ['The tenant is already on the selected plan.'],
            ]);
        }

        if ($current !== null) {
            $this->cancel($current, (bool) ($options['immediate'] ?? true));
        }

        $metadata = $options['metadata'] ?? [];
        if ($current !== null) {
            $metadata['previous_subscription_id'] = $current->id;
        }

        $options['metadata'] = $metadata;

        return $this->subscribe($tenant, $plan, $options);
    }

    /**
     * Activate a free plan immediately for the tenant.
     *
     * @param  Tenant  $tenant
     * @param  Plan  $plan
     * @param  array{metadata?: array<string, mixed>}  $options
     * @return Subscription
     */
    protected function activateFreePlan(Tenant $tenant, Plan $plan, array $options = []): Subscription
    {
        return $this->centralTransaction(function () use ($tenant, $plan, $options): Subscription {
            $this->cancelConflictingSubscriptions((string) $tenant->getTenantKey());

            $period = $this->periodBounds($plan, now());

            /** @var Subscription $subscription */
            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'plan_id' => $plan->id,
                'provider' => null,
                'status' => $plan->trial_days > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'starts_at' => $period['starts_at'],
                'ends_at' => null,
                'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'current_period_start' => $period['current_period_start'],
                'current_period_end' => $period['current_period_end'],
                'auto_renew' => true,
                'cancel_at_period_end' => false,
                'metadata' => $options['metadata'] ?? null,
            ]);

            $subscription = $subscription->load(['plan.features']);

            event(new SubscriptionActivated($subscription));

            return $subscription;
        });
    }

    /**
     * Create a pending paid subscription and initialize gateway checkout.
     *
     * @param  Tenant  $tenant
     * @param  Plan  $plan
     * @param  array{email?: string|null, callback_url?: string|null, customer_name?: string|null, metadata?: array<string, mixed>}  $options
     * @return array{subscription: Subscription, payment: PaymentInitiationResult}
     *
     * @throws RuntimeException
     * @throws ValidationException
     */
    protected function initializePaidSubscription(Tenant $tenant, Plan $plan, array $options = []): array
    {
        $provider = PaymentProvider::from((string) config('payment.default', PaymentProvider::Paystack->value));
        $gateway = $this->paymentManager->driver($provider->value);

        if (! $gateway->supportsCurrency($plan->currency)) {
            throw ValidationException::withMessages([
                'plan_id' => ["The payment driver does not support currency [{$plan->currency}]."],
            ]);
        }

        $email = $options['email'] ?? $tenant->email;
        if ($email === null || $email === '') {
            throw ValidationException::withMessages([
                'email' => ['An email address is required to initialize payment.'],
            ]);
        }

        $reference = $this->generateReference($tenant);

        [$subscription, $transaction] = $this->centralTransaction(function () use ($tenant, $plan, $options, $provider, $reference): array {
            /** @var Subscription $subscription */
            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'plan_id' => $plan->id,
                'provider' => $provider,
                'status' => SubscriptionStatus::Pending,
                'auto_renew' => true,
                'cancel_at_period_end' => false,
                'metadata' => $options['metadata'] ?? null,
            ]);

            /** @var PaymentTransaction $transaction */
            $transaction = PaymentTransaction::query()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'subscription_id' => $subscription->id,
                'provider' => $provider,
                'reference' => $reference,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'status' => PaymentTransactionStatus::Pending,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_slug' => $plan->slug,
                ],
            ]);

            return [$subscription, $transaction];
        });

        try {
            $initiation = $gateway->initializePayment(new PaymentInitiationRequest(
                amount: (string) $plan->price,
                currency: $plan->currency,
                email: $email,
                reference: $reference,
                callbackUrl: $options['callback_url'] ?? null,
                metadata: array_merge($options['metadata'] ?? [], [
                    'tenant_id' => (string) $tenant->getTenantKey(),
                    'subscription_id' => $subscription->id,
                    'payment_transaction_id' => $transaction->id,
                    'plan_id' => $plan->id,
                ]),
                customerName: $options['customer_name'] ?? $tenant->name,
            ));
        } catch (Throwable $exception) {
            $transaction->update(['status' => PaymentTransactionStatus::Failed]);
            $subscription->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);

            throw $exception;
        }

        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], [
                'authorization_url' => $initiation->authorizationUrl,
                'access_code' => $initiation->accessCode,
            ]),
        ]);

        return [
            'subscription' => $subscription->load(['plan.features']),
            'payment' => $initiation,
        ];
    }

    /**
     * Cancel other access-granting or pending subscriptions for the tenant.
     *
     * @param  string  $tenantId
     * @param  ?int  $exceptSubscriptionId
     * @return void
     */
    protected function cancelConflictingSubscriptions(string $tenantId, ?int $exceptSubscriptionId = null): void
    {
        $query = Subscription::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trialing->value,
                SubscriptionStatus::Pending->value,
            ]);

        if ($exceptSubscriptionId !== null) {
            $query->whereKeyNot($exceptSubscriptionId);
        }

        $query->get()->each(function (Subscription $subscription): void {
            $this->cancel($subscription, true);
        });
    }

    /**
     * Calculate billing period bounds for a plan starting at a given moment.
     *
     * @param  Plan  $plan
     * @param  CarbonInterface  $startsAt
     * @return array{starts_at: CarbonInterface, current_period_start: CarbonInterface, current_period_end: CarbonInterface}
     */
    protected function periodBounds(Plan $plan, CarbonInterface $startsAt): array
    {
        $count = max(1, (int) $plan->billing_interval_count);
        $endsAt = match ($plan->billing_interval) {
            BillingInterval::Yearly => $startsAt->copy()->addYears($count),
            default => $startsAt->copy()->addMonths($count),
        };

        return [
            'starts_at' => $startsAt,
            'current_period_start' => $startsAt,
            'current_period_end' => $endsAt,
        ];
    }

    /**
     * Generate a unique payment reference for the tenant.
     *
     * @param  Tenant  $tenant
     * @return string
     */
    protected function generateReference(Tenant $tenant): string
    {
        return 'sub_'.Str::lower((string) $tenant->getTenantKey()).'_'.Str::lower(Str::random(16));
    }
}
