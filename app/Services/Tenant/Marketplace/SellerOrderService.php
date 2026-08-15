<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\SellerOrder;
use App\Models\Tenant\SellerOrderItem;
use App\Models\Tenant\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Splits customer orders into seller sub-orders and manages listing.
 */
class SellerOrderService
{
    /**
     * Split a customer order into seller sub-orders grouped by seller_id.
     */
    public function splitFromOrder(Order $order): void
    {
        $order->loadMissing('items.sellerOffer');

        $grouped = $order->items
            ->filter(fn (OrderItem $item): bool => $item->seller_id !== null)
            ->groupBy('seller_id');

        if ($grouped->isEmpty()) {
            return;
        }

        foreach ($grouped as $sellerId => $items) {
            $subtotal = '0.00';

            foreach ($items as $item) {
                $subtotal = Money::add($subtotal, (string) $item->subtotal);
            }

            /** @var SellerOrder $sellerOrder */
            $sellerOrder = SellerOrder::query()->create([
                'order_id' => $order->id,
                'seller_id' => (int) $sellerId,
                'status' => SellerOrderStatus::Pending,
                'subtotal' => $subtotal,
                'discount_total' => '0.00',
                'tax_total' => '0.00',
                'shipping_total' => '0.00',
                'commission_total' => '0.00',
                'seller_total' => $subtotal,
            ]);

            foreach ($items as $item) {
                SellerOrderItem::query()->create([
                    'seller_order_id' => $sellerOrder->id,
                    'order_item_id' => $item->id,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'subtotal' => (string) $item->subtotal,
                    'total' => (string) $item->total,
                ]);
            }
        }
    }

    /**
     * Confirm all seller orders after payment success.
     */
    public function confirmForPaidOrder(Order $order): void
    {
        SellerOrder::query()
            ->where('order_id', $order->id)
            ->where('status', SellerOrderStatus::Pending)
            ->update(['status' => SellerOrderStatus::Confirmed->value]);
    }

    /**
     * @param  array{
     *     seller_id?: int|null,
     *     order_id?: int|null,
     *     status?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, SellerOrder>
     */
    public function list(array $params = [], ?User $actor = null): LengthAwarePaginator
    {
        $query = SellerOrder::query()
            ->with(['order', 'seller', 'items.orderItem'])
            ->latest('id');

        if ($actor?->isSellerUser()) {
            $query->where('seller_id', $actor->seller_id);
        } elseif (! empty($params['seller_id'])) {
            $query->where('seller_id', $params['seller_id']);
        }

        if (! empty($params['order_id'])) {
            $query->where('order_id', $params['order_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate($this->perPage($params));
    }

    public function show(SellerOrder $sellerOrder): SellerOrder
    {
        return $sellerOrder->load([
            'order.customer',
            'seller',
            'items.orderItem.product',
            'items.orderItem.productVariant',
            'commission',
        ]);
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
