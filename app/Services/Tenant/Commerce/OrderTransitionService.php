<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Events\OrderCancelled;
use App\Models\Tenant\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enforces allowed order status transitions.
 */
class OrderTransitionService
{
    /**
     * Admin/manual transitions. Confirmed is driven by payment success, not PATCH.
     *
     * @var array<string, list<OrderStatus>>
     */
    private const array ALLOWED = [
        OrderStatus::Pending->value => [OrderStatus::Cancelled],
        OrderStatus::Confirmed->value => [OrderStatus::Processing],
        OrderStatus::Processing->value => [OrderStatus::Fulfilled],
        OrderStatus::Fulfilled->value => [OrderStatus::Completed],
        OrderStatus::Completed->value => [OrderStatus::Refunded],
        OrderStatus::Cancelled->value => [],
        OrderStatus::Refunded->value => [],
    ];

    public function __construct(
        private readonly OrderInventoryService $orderInventory,
    ) {}

    /**
     * Transition an order to a new status when allowed.
     *
     * @throws ValidationException
     */
    public function transition(Order $order, OrderStatus $to): Order
    {
        return DB::transaction(function () use ($order, $to): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $from = $locked->status;
            $allowed = self::ALLOWED[$from->value] ?? [];

            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition order from {$from->value} to {$to->value}.",
                ]);
            }

            if ($to === OrderStatus::Cancelled) {
                if ($locked->payment_status === OrderPaymentStatus::Paid) {
                    throw ValidationException::withMessages([
                        'status' => 'Paid orders cannot be cancelled without a refund workflow.',
                    ]);
                }

                $locked->status = $to;
                $locked->cancelled_at = now();
                $locked->save();
                $this->orderInventory->releaseForOrder($locked);
                event(new OrderCancelled($locked));

                return $locked->fresh(['items', 'customer', 'shippingMethod']) ?? $locked;
            }

            $locked->status = $to;
            $locked->save();

            return $locked->fresh(['items', 'customer', 'shippingMethod']) ?? $locked;
        });
    }
}
