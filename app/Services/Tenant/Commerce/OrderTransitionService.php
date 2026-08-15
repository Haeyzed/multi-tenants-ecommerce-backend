<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

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
     * @var array<string, list<OrderStatus>>
     */
    private const array ALLOWED = [
        OrderStatus::Pending->value => [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Cancelled],
        OrderStatus::Confirmed->value => [OrderStatus::Processing, OrderStatus::Cancelled],
        OrderStatus::Processing->value => [OrderStatus::Fulfilled, OrderStatus::Cancelled],
        OrderStatus::Fulfilled->value => [OrderStatus::Completed, OrderStatus::Cancelled],
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

            $locked->status = $to;

            if ($to === OrderStatus::Confirmed && $locked->confirmed_at === null) {
                $locked->confirmed_at = now();
            }

            if ($to === OrderStatus::Cancelled) {
                $locked->cancelled_at = now();
                $this->orderInventory->releaseForOrder($locked);
            }

            $locked->save();

            if ($to === OrderStatus::Cancelled) {
                event(new OrderCancelled($locked));
            }

            return $locked->fresh(['items', 'customer', 'shippingMethod']) ?? $locked;
        });
    }
}
