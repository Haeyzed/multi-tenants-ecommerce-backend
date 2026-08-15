<?php

declare(strict_types=1);

namespace App\Listeners\Notification;

use App\Enums\Notification\NotificationChannel;
use App\Events\ProductReviewApproved;
use App\Services\Notification\NotificationService;

class SendReviewApprovedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Notify the customer that their review was approved.
     */
    public function handle(ProductReviewApproved $event): void
    {
        $review = $event->review->loadMissing(['customer', 'product']);
        $customer = $review->customer;

        if ($customer === null) {
            return;
        }

        $this->notifications->send(
            $customer,
            'review.approved',
            [
                'user_name' => $customer->full_name,
                'product_name' => $review->product?->name,
                'rating' => $review->rating,
            ],
            [
                NotificationChannel::Email->value,
                NotificationChannel::Database->value,
            ],
        );
    }
}
