<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\LeaveRequested;
use App\Models\Tenant\User;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Support\NotifiableDisplayName;

class SendLeaveRequestedNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly HrSettingsService $hrSettings,
    ) {}

    public function handle(LeaveRequested $event): void
    {
        if (! $this->hrSettings->notifyLeave()) {
            return;
        }

        $user = $event->leaveRequest->employee?->user;

        if ($user === null) {
            return;
        }

        $payload = [
            'user_name' => NotifiableDisplayName::resolve($user),
            'email' => $user->email,
            'start_date' => $event->leaveRequest->start_date->toDateString(),
            'end_date' => $event->leaveRequest->end_date->toDateString(),
            'type' => $event->leaveRequest->leaveType?->code ?? $event->leaveRequest->loadMissing('leaveType')->leaveType?->code,
        ];

        $this->notifications->send(
            $user,
            'hr.leave.requested',
            $payload,
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );

        $reviewers = User::query()
            ->permission(['hr.leave.manage', 'hr.leave.approve'])
            ->whereKeyNot($user->id)
            ->get();

        foreach ($reviewers as $reviewer) {
            $this->notifications->send(
                $reviewer,
                'hr.leave.requested',
                $payload,
                [
                    NotificationChannel::Email->value,
                    NotificationChannel::Database->value,
                ],
            );
        }
    }
}
