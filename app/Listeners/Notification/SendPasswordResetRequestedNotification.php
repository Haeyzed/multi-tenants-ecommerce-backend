<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\PasswordResetRequested;
use App\Services\Notification\NotificationService;
use App\Support\NotifiableDisplayName;

class SendPasswordResetRequestedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PasswordResetRequested $event): void
    {
        $this->notifications->send(
            $event->user,
            'auth.password_reset',
            [
                'user_name' => NotifiableDisplayName::resolve($event->user),
                'email' => $event->user->email ?? '',
                'token' => $event->token,
            ],
            [NotificationChannel::Email->value],
        );
    }
}
