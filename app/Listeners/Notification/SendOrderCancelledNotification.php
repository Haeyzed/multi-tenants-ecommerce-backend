<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\OrderCancelled;
use App\Services\Notification\NotificationService;

class SendOrderCancelledNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(OrderCancelled $event): void
    {
        $order = $event->order->loadMissing('customer');
        $customer = $order->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'order.cancelled',
            [
                'user_name' => $customer->full_name,
                'order_number' => $order->order_number,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
