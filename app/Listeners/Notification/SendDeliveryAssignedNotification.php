<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\DeliveryAssigned;
use App\Services\Notification\NotificationService;

class SendDeliveryAssignedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(DeliveryAssigned $event): void
    {
        $delivery = $event->delivery->loadMissing(['driver', 'order']);
        $driver = $delivery->driver;

        if ($driver === null) {
            return;
        }

        $this->notifications->send(
            $driver,
            'delivery.assigned',
            [
                'user_name' => $driver->full_name,
                'delivery_id' => (string) $delivery->id,
                'order_number' => $delivery->order?->order_number ?? '',
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
