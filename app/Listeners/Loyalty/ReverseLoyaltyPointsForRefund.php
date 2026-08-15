<?php

declare(strict_types=1);

namespace App\Listeners\Loyalty;

use App\Events\RefundCompleted;
use App\Services\Tenant\Loyalty\LoyaltyService;

/**
 * Claws back loyalty points proportionally to a completed refund.
 */
class ReverseLoyaltyPointsForRefund
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    /**
     * Reverse the earned points covered by the refunded amount.
     */
    public function handle(RefundCompleted $event): void
    {
        $refund = $event->refund->loadMissing('order.customer');
        $order = $refund->order;

        if ($order === null) {
            return;
        }

        $this->loyalty->reverseEarnForRefund($order, $refund);
    }
}
