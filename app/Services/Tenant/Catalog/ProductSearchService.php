<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Contracts\Catalog\ProductSearchDriver;
use App\Enums\Tenant\Catalog\ProductSearchSort;
use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Entry point for advanced product search, filtering, and sorting.
 *
 * All catalogue reads go through the bound ProductSearchDriver so the query backend
 * can be replaced without changing controllers or other services.
 */
class ProductSearchService
{
    /**
     * Create a new class instance.
     *
     * @param  ProductSearchDriver  $driver
     */
    public function __construct(private readonly ProductSearchDriver $driver) {}

    /**
     * Search the catalogue.
     *
     * @param  array{
     *     keyword?: string|null,
     *     search?: string|null,
     *     category_id?: int|null,
     *     brand_id?: int|null,
     *     collection_id?: int|null,
     *     tag_id?: int|null,
     *     min_price?: numeric-string|float|int|null,
     *     max_price?: numeric-string|float|int|null,
     *     currency?: string|null,
     *     min_rating?: float|int|null,
     *     is_featured?: bool|null,
     *     availability?: string|null,
     *     seller_id?: int|null,
     *     attribute_value_ids?: list<int>|null,
     *     sort?: string|null,
     *     per_page?: int|null,
     *     storefront_only?: bool
     * }  $params
     * @return LengthAwarePaginator<int, Product>
     */
    public function search(array $params = []): LengthAwarePaginator
    {
        return $this->driver->search($params);
    }

    /**
     * Sort modes accepted by the `sort` parameter.
     *
     * @return list<string>
     */
    public function sorts(): array
    {
        return array_map(fn (ProductSearchSort $sort): string => $sort->value, ProductSearchSort::cases());
    }
}
