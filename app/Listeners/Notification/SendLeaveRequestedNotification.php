<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\LeaveRequested;
use App\Services\Notification\NotificationService;
use App\Support\NotifiableDisplayName;

class SendLeaveRequestedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(LeaveRequested $event): void
    {
        $user = $event->leaveRequest->employee?->user;

        if ($user === null) {
            return;
        }

        $this->notifications->send(
            $user,
            'hr.leave.requested',
            [
                'user_name' => NotifiableDisplayName::resolve($user),
                'email' => $user->email,
                'start_date' => $event->leaveRequest->start_date->toDateString(),
                'end_date' => $event->leaveRequest->end_date->toDateString(),
                'type' => $event->leaveRequest->type->value,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
