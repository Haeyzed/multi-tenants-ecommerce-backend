<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductView;
use App\Services\Tenant\Commerce\CommerceAnalyticsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Records storefront product views and reads them back as "recently viewed".
 */
class ProductViewService
{
    /**
     * Create a new class instance.
     *
     * @param  CommerceAnalyticsService  $analytics
     */
    public function __construct(private readonly CommerceAnalyticsService $analytics) {}

    /**
     * Record a product view for a customer and/or anonymous session.
     *
     * @param  Product  $product
     * @param  ?Customer  $customer
     * @param  ?string  $sessionKey
     * @return ?ProductView
     */
    public function record(Product $product, ?Customer $customer = null, ?string $sessionKey = null): ?ProductView
    {
        $this->analytics->record('product.viewed', $product, $customer, [
            'product_id' => $product->id,
            'session_key' => $sessionKey,
        ]);

        if ($customer === null && $sessionKey === null) {
            return null;
        }

        if (! Schema::hasTable('product_views')) {
            return null;
        }

        return ProductView::query()->create([
            'customer_id' => $customer?->id,
            'product_id' => $product->id,
            'session_key' => $sessionKey,
            'viewed_at' => now(),
        ]);
    }

    /**
     * Distinct recently viewed products, most recent first.
     *
     * @param  ?Customer  $customer
     * @param  ?string  $sessionKey
     * @param  int  $limit
     * @param  ?int  $excludeProductId
     * @return Collection<int, Product>
     */
    public function recentlyViewed(
        ?Customer $customer = null,
        ?string $sessionKey = null,
        int $limit = 12,
        ?int $excludeProductId = null,
    ): Collection {
        return $this->recentlyViewedQuery($customer, $sessionKey, $excludeProductId)
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Paginated recently viewed products.
     *
     * @param  ?Customer  $customer
     * @param  ?string  $sessionKey
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateRecentlyViewed(
        ?Customer $customer = null,
        ?string $sessionKey = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->recentlyViewedQuery($customer, $sessionKey)
            ->paginate(max(1, min($perPage, 100)));
    }

    /**
     * Build the distinct recently viewed products query.
     *
     * @param  ?Customer  $customer
     * @param  ?string  $sessionKey
     * @param  ?int  $excludeProductId
     * @return Builder<Product>
     */
    protected function recentlyViewedQuery(
        ?Customer $customer,
        ?string $sessionKey,
        ?int $excludeProductId = null,
    ): Builder {
        $customerId = $customer?->id;

        $query = Product::query()
            ->select('products.*')
            ->storefrontVisible()
            ->with(['brand', 'media', 'tags', 'prices' => fn ($query) => $query->where('is_active', true)]);

        if ($customerId === null && $sessionKey === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('id', ProductView::query()
                ->select('product_id')
                ->forViewer($customerId, $sessionKey))
            ->when($excludeProductId !== null, fn (Builder $query) => $query->whereKeyNot($excludeProductId))
            ->addSelect(['last_viewed_at' => ProductView::query()
                ->selectRaw('MAX(viewed_at)')
                ->whereColumn('product_id', 'products.id')
                ->forViewer($customerId, $sessionKey),
            ])
            // Views recorded within the same second tie on viewed_at, so fall back to
            // insertion order to keep "most recent" stable.
            ->addSelect(['last_view_id' => ProductView::query()
                ->selectRaw('MAX(id)')
                ->whereColumn('product_id', 'products.id')
                ->forViewer($customerId, $sessionKey),
            ])
            ->orderByDesc('last_viewed_at')
            ->orderByDesc('last_view_id');
    }
}
