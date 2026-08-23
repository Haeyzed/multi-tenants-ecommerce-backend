<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\SubscriptionExpired;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Notification\NotificationService;
use Throwable;

class SendSubscriptionExpiredNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SubscriptionExpired $event): void
    {
        $subscription = $event->subscription->loadMissing('plan');
        $tenant = Tenant::query()->find($subscription->tenant_id);

        if ($tenant === null) {
            return;
        }

        $status = $subscription->status->value ?? (string) $subscription->status;

        try {
            $tenant->run(function () use ($subscription, $status): void {
                foreach (TenantUser::role('admin')->get() as $admin) {
                    $this->notifications->send($admin, 'subscription.expired', [
                        'plan_name' => $subscription->plan?->name ?? '',
                        'subscription_id' => (string) $subscription->id,
                        'status' => $status,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
