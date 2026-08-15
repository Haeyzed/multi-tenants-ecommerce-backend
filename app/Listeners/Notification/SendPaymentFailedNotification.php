<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\PaymentFailed;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Notification\NotificationService;
use Throwable;

class SendPaymentFailedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PaymentFailed $event): void
    {
        $transaction = $event->transaction->loadMissing('subscription.plan');
        $tenant = Tenant::query()->find($transaction->tenant_id);

        if ($tenant === null) {
            return;
        }

        $payload = [
            'amount' => (string) $transaction->amount,
            'currency' => (string) $transaction->currency,
            'reference' => (string) $transaction->reference,
            'plan_name' => $transaction->subscription?->plan?->name ?? '',
            'reason' => $event->reason ?? '',
        ];

        try {
            $tenant->run(function () use ($payload): void {
                foreach (TenantUser::role('admin')->get() as $admin) {
                    $this->notifications->send($admin, 'payment.failed', $payload);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
