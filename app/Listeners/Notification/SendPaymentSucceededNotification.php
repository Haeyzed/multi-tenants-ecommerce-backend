<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\PaymentSucceeded;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\User as LandlordUser;
use App\Models\Tenant\User as TenantUser;
use App\Services\Notification\NotificationService;
use Throwable;

class SendPaymentSucceededNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PaymentSucceeded $event): void
    {
        $transaction = $event->transaction->loadMissing('subscription.plan');
        $payload = [
            'amount' => (string) $transaction->amount,
            'currency' => (string) $transaction->currency,
            'reference' => (string) $transaction->reference,
            'plan_name' => $transaction->subscription?->plan?->name ?? '',
        ];

        $tenant = Tenant::query()->find($transaction->tenant_id);

        if ($tenant !== null) {
            try {
                $tenant->run(function () use ($payload): void {
                    foreach (TenantUser::role('admin')->get() as $admin) {
                        $this->notifications->send($admin, 'payment.successful', $payload);
                    }
                });
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        foreach (LandlordUser::permission('subscriptions.view')->get() as $admin) {
            $this->notifications->send($admin, 'payment.successful', [
                ...$payload,
                'tenant_id' => (string) $transaction->tenant_id,
            ]);
        }
    }
}
