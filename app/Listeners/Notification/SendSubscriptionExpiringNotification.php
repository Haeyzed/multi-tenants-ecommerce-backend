<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\SubscriptionExpiring;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Notification\NotificationService;
use Throwable;

class SendSubscriptionExpiringNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SubscriptionExpiring $event): void
    {
        $subscription = $event->subscription->loadMissing('plan');
        $tenant = Tenant::query()->find($subscription->tenant_id);

        if ($tenant === null) {
            return;
        }

        $endsAt = $subscription->accessEndsAt()?->toDateTimeString() ?? '';

        try {
            $tenant->run(function () use ($subscription, $endsAt): void {
                foreach (TenantUser::role('admin')->get() as $admin) {
                    $this->notifications->send($admin, 'subscription.expiring', [
                        'plan_name' => $subscription->plan?->name ?? '',
                        'subscription_id' => (string) $subscription->id,
                        'ends_at' => $endsAt,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
