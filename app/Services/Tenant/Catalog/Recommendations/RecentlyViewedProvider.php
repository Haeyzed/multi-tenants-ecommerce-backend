<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog\Recommendations;

use App\Contracts\Catalog\ProductRecommendationProvider;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Services\Tenant\Catalog\ProductViewService;
use Illuminate\Support\Collection;

/**
 * Recommends products the viewer has recently looked at.
 */
class RecentlyViewedProvider implements ProductRecommendationProvider
{
    public function __construct(private readonly ProductViewService $productViews) {}

    public function key(): string
    {
        return 'recently_viewed';
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
        return $this->productViews->recentlyViewed(
            customer: $customer,
            sessionKey: $sessionKey,
            limit: $limit,
            excludeProductId: $product?->id,
        );
    }
}
