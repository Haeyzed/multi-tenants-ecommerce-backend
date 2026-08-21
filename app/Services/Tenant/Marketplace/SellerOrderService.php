<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerOrder;
use App\Models\Tenant\SellerOrderItem;
use App\Support\Money;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Splits customer orders into seller sub-orders and manages listing.
 */
class SellerOrderService
{
    /**
     * Split a customer order into seller sub-orders grouped by seller_id.
     *
     * @param  Order  $order
     * @return void
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
            $sellerId = (int) $sellerId;

            if (SellerOrder::query()->where('order_id', $order->id)->where('seller_id', $sellerId)->exists()) {
                continue;
            }

            $subtotal = '0.00';

            foreach ($items as $item) {
                $subtotal = Money::add($subtotal, (string) $item->subtotal);
            }

            /** @var SellerOrder $sellerOrder */
            $sellerOrder = SellerOrder::query()->create([
                'order_id' => $order->id,
                'seller_id' => $sellerId,
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
     *
     * @param  Order  $order
     * @return void
     */
    public function confirmForPaidOrder(Order $order): void
    {
        SellerOrder::query()
            ->where('order_id', $order->id)
            ->where('status', SellerOrderStatus::Pending)
            ->update(['status' => SellerOrderStatus::Confirmed->value]);
    }

    /**
     * seller_id?: int|null, order_id?: int|null, status?: string|null, per_page?: int|null }  $params
     *
     * @param  array{
     *     seller_id?: int|null,
     *     order_id?: int|null,
     *     status?: string|null,
     *     per_page?: int|null
     * }  $params
     * @param  ?Authenticatable  $actor
     * @return LengthAwarePaginator<int, SellerOrder>
     */
    public function list(array $params = [], ?Authenticatable $actor = null): LengthAwarePaginator
    {
        $query = SellerOrder::query()
            ->with(['order', 'seller', 'items.orderItem'])
            ->latest('id');

        if ($actor instanceof Seller) {
            $query->where('seller_id', $actor->id);
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

    /**
     * Retrieve a single resource.
     *
     * @param  SellerOrder  $sellerOrder
     * @return SellerOrder
     */
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
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
