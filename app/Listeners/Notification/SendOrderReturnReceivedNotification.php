<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\OrderReturnReceived;
use App\Services\Notification\NotificationService;

class SendOrderReturnReceivedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Notify the customer that a returned package was received.
     */
    public function handle(OrderReturnReceived $event): void
    {
        $return = $event->orderReturn->loadMissing(['customer', 'order']);
        $customer = $return->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'return.received',
            [
                'user_name' => $customer->full_name,
                'return_number' => $return->return_number,
                'order_number' => $return->order?->order_number,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
