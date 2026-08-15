<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\RefundCompleted;
use App\Services\Notification\NotificationService;

class SendRefundCompletedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Notify the customer that a refund was completed.
     */
    public function handle(RefundCompleted $event): void
    {
        $refund = $event->refund->loadMissing(['order.customer']);
        $customer = $refund->order?->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'refund.completed',
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
