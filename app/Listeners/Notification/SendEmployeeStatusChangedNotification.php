<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\EmployeeStatusChanged;
use App\Services\Notification\NotificationService;
use App\Support\NotifiableDisplayName;

class SendEmployeeStatusChangedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(EmployeeStatusChanged $event): void
    {
        $user = $event->employee->user;

        if ($user === null) {
            return;
        }

        $this->notifications->send(
            $user,
            'hr.employee.status_changed',
            [
                'user_name' => NotifiableDisplayName::resolve($user),
                'email' => $user->email,
                'previous_status' => $event->previous->value,
                'current_status' => $event->current->value,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
