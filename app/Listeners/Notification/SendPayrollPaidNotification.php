<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\PayrollPaid;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Support\NotifiableDisplayName;

class SendPayrollPaidNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly HrSettingsService $hrSettings,
    ) {}

    public function handle(PayrollPaid $event): void
    {
        if (! $this->hrSettings->notifyPayroll()) {
            return;
        }

        $user = $event->payrollRun->paidByUser;

        if ($user === null) {
            return;
        }

        $this->notifications->send(
            $user,
            'hr.payroll.paid',
            [
                'user_name' => NotifiableDisplayName::resolve($user),
                'email' => $user->email,
                'reference' => $event->payrollRun->reference,
                'net_total' => (string) $event->payrollRun->net_total,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
