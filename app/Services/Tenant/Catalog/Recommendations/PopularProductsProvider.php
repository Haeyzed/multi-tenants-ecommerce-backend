<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog\Recommendations;

use App\Contracts\Catalog\ProductRecommendationProvider;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Recommends best sellers derived from order line item volume.
 */
class PopularProductsProvider implements ProductRecommendationProvider
{
    public function key(): string
    {
        return 'popular';
    }

    /**
     * {@inheritDoc}
     */
    public function recommend(
        ?Product $product = null,
        ?Customer $customer = null,
        int $limit = 8,
        ?string $sessionKey = null,
    ): Collection {
        if (! Schema::hasTable('order_items')) {
            return collect();
        }

        $limit = max(1, $limit);

        $rankedIds = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereNotNull('order_items.product_id')
            ->when($product !== null, fn ($query) => $query->where('order_items.product_id', '!=', $product->id))
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit($limit)
            ->pluck('order_items.product_id')
            ->all();

        if ($rankedIds === []) {
            return collect();
        }

        $products = Product::query()
            ->storefrontVisible()
            ->with(['brand', 'media', 'tags', 'prices' => fn ($query) => $query->where('is_active', true)])
            ->whereIn('id', $rankedIds)
            ->get();

        return $products
            ->sortBy(fn (Product $item): int => (int) array_search($item->id, $rankedIds, true))
            ->values();
    }
}
