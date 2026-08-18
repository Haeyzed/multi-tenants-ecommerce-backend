<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\PayslipAvailable;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Support\NotifiableDisplayName;

class SendPayslipAvailableNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly HrSettingsService $hrSettings,
    ) {}

    public function handle(PayslipAvailable $event): void
    {
        if (! $this->hrSettings->notifyPayroll()) {
            return;
        }

        $item = $event->payrollItem->loadMissing(['employee.user', 'payrollRun']);
        $user = $item->employee?->user;

        if ($user === null) {
            return;
        }

        $this->notifications->send(
            $user,
            'hr.payslip.available',
            [
                'user_name' => NotifiableDisplayName::resolve($user),
                'email' => $user->email,
                'reference' => $item->payrollRun?->reference ?? '',
                'net_pay' => (string) $item->net_pay,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
