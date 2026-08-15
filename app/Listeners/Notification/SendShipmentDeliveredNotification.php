<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\ShipmentDelivered;
use App\Services\Notification\NotificationService;

class SendShipmentDeliveredNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(ShipmentDelivered $event): void
    {
        $shipment = $event->shipment->loadMissing('order.customer');
        $customer = $shipment->order?->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'shipment.delivered',
            [
                'user_name' => $customer->full_name,
                'order_number' => $shipment->order->order_number,
                'tracking_number' => $shipment->tracking_number ?? '',
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
