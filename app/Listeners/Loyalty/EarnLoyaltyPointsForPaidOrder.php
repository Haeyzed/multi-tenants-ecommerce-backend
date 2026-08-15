<?php

declare(strict_types=1);

namespace App\Listeners\Loyalty;

use App\Events\OrderPaid;
use App\Services\Tenant\Loyalty\LoyaltyService;

/**
 * Awards loyalty points once an order is paid.
 */
class EarnLoyaltyPointsForPaidOrder
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    /**
     * Credit the customer's loyalty account for the paid order.
     */
    public function handle(OrderPaid $event): void
    {
        $this->loyalty->earnForPaidOrder($event->order->loadMissing('customer'));
    }
}
