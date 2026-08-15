<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\CouponApplied;
use App\Services\Notification\NotificationService;

class SendCouponAppliedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Notify the customer that a coupon was applied to their order.
     */
    public function handle(CouponApplied $event): void
    {
        $this->notifications->send(
            $event->customer,
            'coupon.applied',
            [
                'user_name' => $event->customer->full_name,
                'coupon_code' => $event->coupon->code,
                'discount_amount' => $event->discountAmount,
                'order_number' => $event->order->order_number,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
