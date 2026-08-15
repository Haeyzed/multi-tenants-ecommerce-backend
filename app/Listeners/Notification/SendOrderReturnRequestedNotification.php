<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\OrderReturnRequested;
use App\Services\Notification\NotificationService;

class SendOrderReturnRequestedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Notify the customer that a return request was submitted.
     */
    public function handle(OrderReturnRequested $event): void
    {
        $return = $event->orderReturn->loadMissing(['customer', 'order']);
        $customer = $return->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'return.requested',
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
