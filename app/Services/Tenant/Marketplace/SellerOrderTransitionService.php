<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Models\Tenant\SellerOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enforces allowed seller order status transitions.
 */
class SellerOrderTransitionService
{
    /**
     * @var array<string, list<SellerOrderStatus>>
     */
    private const array ALLOWED = [
        SellerOrderStatus::Pending->value => [SellerOrderStatus::Confirmed, SellerOrderStatus::Cancelled],
        SellerOrderStatus::Confirmed->value => [SellerOrderStatus::Processing, SellerOrderStatus::Cancelled],
        SellerOrderStatus::Processing->value => [SellerOrderStatus::ReadyToShip, SellerOrderStatus::Cancelled],
        SellerOrderStatus::ReadyToShip->value => [SellerOrderStatus::Shipped, SellerOrderStatus::Cancelled],
        SellerOrderStatus::Shipped->value => [SellerOrderStatus::Delivered, SellerOrderStatus::Cancelled],
        SellerOrderStatus::Delivered->value => [SellerOrderStatus::Refunded],
        SellerOrderStatus::Cancelled->value => [],
        SellerOrderStatus::Refunded->value => [],
    ];

    /**
     * Transition.
     *
     * @param  SellerOrder  $sellerOrder
     * @param  SellerOrderStatus  $to
     * @return SellerOrder
     *
     * @throws ValidationException
     */
    public function transition(SellerOrder $sellerOrder, SellerOrderStatus $to): SellerOrder
    {
        return DB::transaction(function () use ($sellerOrder, $to): SellerOrder {
            /** @var SellerOrder $locked */
            $locked = SellerOrder::query()->whereKey($sellerOrder->getKey())->lockForUpdate()->firstOrFail();

            $from = $locked->status;
            $allowed = self::ALLOWED[$from->value] ?? [];

            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition seller order from {$from->value} to {$to->value}.",
                ]);
            }

            $locked->status = $to;

            if ($to === SellerOrderStatus::Delivered) {
                $locked->fulfilled_at = now();
            }

            $locked->save();

            return $locked->fresh(['order', 'seller', 'items.orderItem']) ?? $locked;
        });
    }
}
