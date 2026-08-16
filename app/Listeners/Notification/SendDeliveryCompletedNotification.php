<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Events\DeliveryCompleted;
use App\Services\Notification\NotificationService;

class SendDeliveryCompletedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(DeliveryCompleted $event): void
    {
        $delivery = $event->delivery->loadMissing('order.customer');

        if ($delivery->status !== DeliveryStatus::Delivered) {
            return;
        }

        $customer = $delivery->order?->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'delivery.completed',
            [
                'user_name' => $customer->full_name,
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
