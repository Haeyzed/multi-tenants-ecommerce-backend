<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\OrderReturnRejected;
use App\Services\Notification\NotificationService;

class SendOrderReturnRejectedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Notify the customer that a return was rejected.
     */
    public function handle(OrderReturnRejected $event): void
    {
        $return = $event->orderReturn->loadMissing(['customer', 'order']);
        $customer = $return->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'return.rejected',
            [
                'user_name' => $customer->full_name,
                'return_number' => $return->return_number,
                'order_number' => $return->order?->order_number,
                'admin_note' => $return->admin_note,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
