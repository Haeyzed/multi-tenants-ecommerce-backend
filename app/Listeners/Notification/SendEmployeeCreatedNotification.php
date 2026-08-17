<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\EmployeeCreated;
use App\Services\Notification\NotificationService;
use App\Support\NotifiableDisplayName;

class SendEmployeeCreatedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(EmployeeCreated $event): void
    {
        $user = $event->employee->user;

        if ($user === null) {
            return;
        }

        $this->notifications->send(
            $user,
            'hr.employee.created',
            [
                'user_name' => NotifiableDisplayName::resolve($user),
                'email' => $user->email,
                'employee_number' => $event->employee->employee_number ?? '',
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
