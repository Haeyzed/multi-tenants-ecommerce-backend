<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\SellerSuspended;
use App\Services\Notification\NotificationService;
use App\Support\NotifiableDisplayName;

class SendSellerSuspendedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SellerSuspended $event): void
    {
        $this->notifications->send(
            $event->seller,
            'seller.suspended',
            [
                'user_name' => NotifiableDisplayName::resolve($event->seller),
                'email' => $event->seller->email,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
