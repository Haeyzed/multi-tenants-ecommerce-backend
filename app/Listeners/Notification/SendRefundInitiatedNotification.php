<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\RefundInitiated;
use App\Services\Notification\NotificationService;

class SendRefundInitiatedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Notify the customer that a refund was initiated.
     */
    public function handle(RefundInitiated $event): void
    {
        $refund = $event->refund->loadMissing(['order.customer']);
        $customer = $refund->order?->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'refund.initiated',
            [
                'user_name' => $customer->full_name,
                'order_number' => $refund->order?->order_number,
                'amount' => (string) $refund->amount,
                'currency' => $refund->currency,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
