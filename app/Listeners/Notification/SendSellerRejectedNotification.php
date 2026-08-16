<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\SellerRejected;
use App\Services\Notification\NotificationService;
use App\Support\NotifiableDisplayName;

class SendSellerRejectedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SellerRejected $event): void
    {
        $this->notifications->send(
            $event->seller,
            'seller.rejected',
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
