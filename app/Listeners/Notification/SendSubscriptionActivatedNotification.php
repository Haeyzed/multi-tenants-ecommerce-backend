<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Landlord\SubscriptionStatus;
use App\Events\SubscriptionActivated;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Notification\NotificationService;
use Throwable;

class SendSubscriptionActivatedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SubscriptionActivated $event): void
    {
        $subscription = $event->subscription->loadMissing('plan');
        $tenant = Tenant::query()->find($subscription->tenant_id);

        if ($tenant === null) {
            return;
        }

        try {
            $tenant->run(function () use ($subscription): void {
                $admins = TenantUser::role('admin')->get();
                $status = $subscription->status;
                $statusValue = $status instanceof SubscriptionStatus
                    ? $status->value
                    : (string) $status;
                $key = $status === SubscriptionStatus::Trialing
                    ? 'subscription.trial_started'
                    : 'subscription.activated';

                foreach ($admins as $admin) {
                    $this->notifications->send($admin, $key, [
                        'plan_name' => $subscription->plan?->name ?? '',
                        'subscription_id' => (string) $subscription->id,
                        'status' => $statusValue,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
