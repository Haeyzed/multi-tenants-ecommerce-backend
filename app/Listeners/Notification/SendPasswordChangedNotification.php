<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\PasswordChanged;
use App\Services\Notification\NotificationService;

class SendPasswordChangedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PasswordChanged $event): void
    {
        $key = $event->reason === 'reset'
            ? 'auth.password_reset_completed'
            : 'auth.password_changed';

        $this->notifications->send($event->user, $key, [
            'user_name' => trim(($event->user->first_name ?? '').' '.($event->user->last_name ?? '')),
            'email' => $event->user->email ?? '',
        ]);
    }
}
