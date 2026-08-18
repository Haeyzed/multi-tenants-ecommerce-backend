<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\PayrollProcessed;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Support\NotifiableDisplayName;

class SendPayrollProcessedNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly HrSettingsService $hrSettings,
    ) {}

    public function handle(PayrollProcessed $event): void
    {
        if (! $this->hrSettings->notifyPayroll()) {
            return;
        }

        $user = $event->payrollRun->processedByUser;

        if ($user === null) {
            return;
        }

        $this->notifications->send(
            $user,
            'hr.payroll.processed',
            [
                'user_name' => NotifiableDisplayName::resolve($user),
                'email' => $user->email,
                'reference' => $event->payrollRun->reference,
                'status' => $event->payrollRun->status->value,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
