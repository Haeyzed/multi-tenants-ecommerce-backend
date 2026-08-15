<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\CustomerRegistered;
use App\Services\Notification\NotificationService;

class SendCustomerRegisteredNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(CustomerRegistered $event): void
    {
        $this->notifications->send(
            $event->customer,
            'customer.welcome',
            [
                'user_name' => $event->customer->full_name,
                'email' => $event->customer->email,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
