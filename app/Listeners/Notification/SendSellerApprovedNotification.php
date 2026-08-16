<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\SellerApproved;
use App\Services\Notification\NotificationService;
use App\Support\NotifiableDisplayName;

class SendSellerApprovedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SellerApproved $event): void
    {
        $this->notifications->send(
            $event->seller,
            'seller.approved',
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
