<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\OrderCreated;
use App\Services\Notification\NotificationService;

class SendOrderCreatedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->order->loadMissing('customer');
        $customer = $order->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'order.placed',
            [
                'user_name' => $customer->full_name,
                'order_number' => $order->order_number,
                'grand_total' => (string) $order->grand_total,
                'currency' => $order->currency,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
