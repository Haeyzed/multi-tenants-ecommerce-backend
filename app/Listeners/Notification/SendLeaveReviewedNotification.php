<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\LeaveReviewed;
use App\Services\Notification\NotificationService;
use App\Support\NotifiableDisplayName;

class SendLeaveReviewedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(LeaveReviewed $event): void
    {
        $user = $event->leaveRequest->employee?->user;

        if ($user === null) {
            return;
        }

        $this->notifications->send(
            $user,
            'hr.leave.reviewed',
            [
                'user_name' => NotifiableDisplayName::resolve($user),
                'email' => $user->email,
                'status' => $event->leaveRequest->status->value,
                'start_date' => $event->leaveRequest->start_date->toDateString(),
                'end_date' => $event->leaveRequest->end_date->toDateString(),
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
