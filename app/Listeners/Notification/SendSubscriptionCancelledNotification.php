<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\SubscriptionCancelled;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Notification\NotificationService;
use Throwable;

class SendSubscriptionCancelledNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SubscriptionCancelled $event): void
    {
        $subscription = $event->subscription->loadMissing('plan');
        $tenant = Tenant::query()->find($subscription->tenant_id);

        if ($tenant === null) {
            return;
        }

        try {
            $tenant->run(function () use ($subscription): void {
                $admins = TenantUser::role('admin')->get();

                foreach ($admins as $admin) {
                    $this->notifications->send($admin, 'subscription.cancelled', [
                        'plan_name' => $subscription->plan?->name ?? '',
                        'subscription_id' => (string) $subscription->id,
                        'status' => $subscription->status->value ?? (string) $subscription->status,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
