<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\CustomerEmailVerificationRequested;
use App\Services\Notification\NotificationService;

class SendCustomerEmailVerificationNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(CustomerEmailVerificationRequested $event): void
    {
        $this->notifications->send(
            $event->customer,
            'customer.email_verify',
            [
                'user_name' => $event->customer->full_name,
                'email' => $event->customer->email,
                'token' => $event->token,
            ],
            [NotificationChannel::Email->value],
        );
    }
}
