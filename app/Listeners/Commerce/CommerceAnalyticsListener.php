<?php

declare(strict_types=1);

namespace App\Listeners\Commerce;

use App\Events\CouponApplied;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\OrderReturnCompleted;
use App\Events\OrderReturnRequested;
use App\Events\RefundCompleted;
use App\Services\Tenant\Commerce\CommerceAnalyticsService;

/**
 * Records commerce analytics events for key lifecycle moments.
 */
class CommerceAnalyticsListener
{
    public function __construct(private readonly CommerceAnalyticsService $analytics) {}

    /**
     * Record an order.created analytics event.
     */
    public function handleOrderCreated(OrderCreated $event): void
    {
        $order = $event->order->loadMissing('customer');

        $this->analytics->record('order.created', $order, $order->customer, [
            'order_number' => $order->order_number,
            'grand_total' => (string) $order->grand_total,
            'currency' => $order->currency,
        ]);
    }

    /**
     * Record an order.paid analytics event.
     */
    public function handleOrderPaid(OrderPaid $event): void
    {
        $order = $event->order->loadMissing('customer');

        $this->analytics->record('order.paid', $order, $order->customer, [
            'order_number' => $order->order_number,
            'grand_total' => (string) $order->grand_total,
            'currency' => $order->currency,
        ]);
    }

    /**
     * Record a return.created analytics event.
     */
    public function handleReturnRequested(OrderReturnRequested $event): void
    {
        $return = $event->orderReturn->loadMissing('customer');

        $this->analytics->record('return.created', $return, $return->customer, [
            'return_number' => $return->return_number,
            'order_id' => $return->order_id,
        ]);
    }

    /**
     * Record a return.completed analytics event.
     */
    public function handleReturnCompleted(OrderReturnCompleted $event): void
    {
        $return = $event->orderReturn->loadMissing('customer');

        $this->analytics->record('return.completed', $return, $return->customer, [
            'return_number' => $return->return_number,
            'order_id' => $return->order_id,
        ]);
    }

    /**
     * Record a refund.completed analytics event.
     */
    public function handleRefundCompleted(RefundCompleted $event): void
    {
        $refund = $event->refund->loadMissing('order.customer');

        $this->analytics->record('refund.completed', $refund, $refund->order?->customer, [
            'amount' => (string) $refund->amount,
            'order_id' => $refund->order_id,
        ]);
    }

    /**
     * Record a coupon.used analytics event.
     */
    public function handleCouponApplied(CouponApplied $event): void
    {
        $this->analytics->record('coupon.used', $event->order, $event->customer, [
            'coupon_code' => $event->coupon->code,
            'discount_amount' => $event->discountAmount,
        ]);
    }
}
