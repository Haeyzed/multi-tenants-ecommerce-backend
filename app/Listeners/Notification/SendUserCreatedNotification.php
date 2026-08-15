<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Events\UserCreated;
use App\Services\Notification\NotificationService;

class SendUserCreatedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(UserCreated $event): void
    {
        $this->notifications->send($event->user, 'user.created', [
            'user_name' => trim(($event->user->first_name ?? '').' '.($event->user->last_name ?? '')),
            'email' => $event->user->email ?? '',
        ]);
    }
}
