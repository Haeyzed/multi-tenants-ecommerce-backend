<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog\Recommendations;

use App\Contracts\Catalog\ProductRecommendationProvider;
use App\Enums\Tenant\Catalog\ProductRelationType;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductRelation;
use Illuminate\Support\Collection;

/**
 * Recommends products curated through ProductRelation rows.
 */
class RelatedProductsProvider implements ProductRecommendationProvider
{
    /**
     * Relation types surfaced by this provider, in priority order.
     *
     * @var list<ProductRelationType>
     */
    protected const array TYPES = [
        ProductRelationType::Related,
        ProductRelationType::Upsell,
        ProductRelationType::CrossSell,
    ];

    public function key(): string
    {
        return 'related';
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
        if ($product === null) {
            return collect();
        }

        $relatedIds = ProductRelation::query()
            ->where('product_id', $product->id)
            ->whereIn('type', array_map(fn (ProductRelationType $type): string => $type->value, self::TYPES))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('related_product_id')
            ->unique()
            ->take(max(1, $limit))
            ->all();

        if ($relatedIds === []) {
            return collect();
        }

        $products = Product::query()
            ->storefrontVisible()
            ->with(['brand', 'media', 'tags', 'prices' => fn ($query) => $query->where('is_active', true)])
            ->whereIn('id', $relatedIds)
            ->get();

        return $products
            ->sortBy(fn (Product $item): int => (int) array_search($item->id, $relatedIds, true))
            ->values();
    }
}
