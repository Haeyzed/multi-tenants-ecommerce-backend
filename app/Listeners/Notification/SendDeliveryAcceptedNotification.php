<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\DeliveryAccepted;
use App\Services\Notification\NotificationService;

class SendDeliveryAcceptedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(DeliveryAccepted $event): void
    {
        $delivery = $event->delivery->loadMissing(['order.customer', 'driver']);
        $customer = $delivery->order?->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'delivery.accepted',
            [
                'user_name' => $customer->full_name,
                'delivery_id' => (string) $delivery->id,
                'order_number' => $delivery->order?->order_number ?? '',
                'driver_name' => $delivery->driver?->full_name ?? '',
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
