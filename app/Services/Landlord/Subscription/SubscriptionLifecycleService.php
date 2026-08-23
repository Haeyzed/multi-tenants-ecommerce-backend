<?php

declare(strict_types=1);

namespace App\Services\Landlord\Subscription;

use App\Enums\Landlord\SubscriptionStatus;
use App\Events\SubscriptionExpired;
use App\Events\SubscriptionExpiring;
use App\Models\Landlord\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Marks due subscriptions expired and emits expiring / expired notifications.
 */
class SubscriptionLifecycleService
{
    /**
     * Process expiring reminders then expire overdue subscriptions.
     *
     * @return array{expiring_notified: int, expired: int}
     */
    public function process(): array
    {
        return [
            'expiring_notified' => $this->notifyExpiring(),
            'expired' => $this->expireDue(),
        ];
    }

    /**
     * Notify tenants whose access ends within the configured window.
     */
    public function notifyExpiring(): int
    {
        $days = max(1, (int) config('notifications.subscription.expiring_days', 7));
        $now = now();
        $horizon = $now->copy()->addDays($days);
        $notified = 0;

        Subscription::query()
            ->with('plan')
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
            ])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, $horizon, &$notified): void {
                foreach ($subscriptions as $subscription) {
                    /** @var Subscription $subscription */
                    $endsAt = $subscription->accessEndsAt();

                    if ($endsAt === null || $endsAt->lte($now) || $endsAt->gt($horizon)) {
                        continue;
                    }

                    $periodKey = $endsAt->toDateString();
                    $metadata = $subscription->metadata ?? [];

                    if (($metadata['expiring_notified_period_end'] ?? null) === $periodKey) {
                        continue;
                    }

                    $metadata['expiring_notified_period_end'] = $periodKey;
                    $subscription->metadata = $metadata;
                    $subscription->save();

                    event(new SubscriptionExpiring($subscription->fresh(['plan']) ?? $subscription));
                    $notified++;
                }
            });

        return $notified;
    }

    /**
     * Transition overdue access-granting subscriptions to expired.
     */
    public function expireDue(): int
    {
        $expired = 0;

        Subscription::query()
            ->with('plan')
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
            ])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$expired): void {
                foreach ($subscriptions as $subscription) {
                    /** @var Subscription $subscription */
                    $endsAt = $subscription->accessEndsAt();

                    if ($endsAt === null || $endsAt->gt(now())) {
                        continue;
                    }

                    $fresh = $this->markExpired($subscription);

                    if ($fresh !== null) {
                        event(new SubscriptionExpired($fresh));
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    /**
     * @return Subscription|null Fresh model when status changed, otherwise null.
     */
    private function markExpired(Subscription $subscription): ?Subscription
    {
        return DB::connection((string) config('tenancy.database.central_connection'))
            ->transaction(function () use ($subscription): ?Subscription {
                /** @var Subscription|null $locked */
                $locked = Subscription::query()
                    ->whereKey($subscription->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    return null;
                }

                if (! in_array($locked->status, [
                    SubscriptionStatus::Active,
                    SubscriptionStatus::Trialing,
                    SubscriptionStatus::PastDue,
                ], true)) {
                    return null;
                }

                $endsAt = $locked->accessEndsAt();

                if ($endsAt === null || $endsAt->gt(now())) {
                    return null;
                }

                $metadata = $locked->metadata ?? [];
                $metadata['expired_at'] = now()->toIso8601String();

                $locked->fill([
                    'status' => SubscriptionStatus::Expired,
                    'ends_at' => $locked->ends_at ?? $endsAt,
                    'auto_renew' => false,
                    'cancel_at_period_end' => false,
                    'metadata' => $metadata,
                ]);
                $locked->save();

                return $locked->fresh(['plan']) ?? $locked->load(['plan']);
            });
    }
}
