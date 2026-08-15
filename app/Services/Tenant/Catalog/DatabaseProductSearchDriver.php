<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Contracts\Catalog\ProductSearchDriver;
use App\Enums\Tenant\Catalog\ProductSearchSort;
use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Relational product search backed by Eloquent scopes.
 *
 * Relevance is computed with plain SQL ranking rather than a full-text index, so
 * results are deterministic but not scored the way a search engine would score them.
 */
class DatabaseProductSearchDriver implements ProductSearchDriver
{
    /**
     * Relations eager loaded for every result row.
     *
     * @var list<string>
     */
    protected const array EAGER_LOADS = ['brand', 'media', 'tags', 'badges.media'];

    /**
     * {@inheritDoc}
     */
    public function search(array $params = []): LengthAwarePaginator
    {
        $filters = $this->normalizeFilters($params);
        $keyword = $filters['search'] ?? null;

        return $this->baseQuery($params)
            ->filter($filters)
            ->applySearchSort($params['sort'] ?? null, $keyword)
            ->paginate($this->perPage($params));
    }

    /**
     * Build the unsorted, unfiltered starting query.
     *
     * @param  array<string, mixed>  $params
     * @return Builder<Product>
     */
    protected function baseQuery(array $params): Builder
    {
        $query = Product::query()->with(array_merge(
            self::EAGER_LOADS,
            ['prices' => fn ($query) => $query->where('is_active', true)],
        ));

        if (($params['storefront_only'] ?? true) !== false) {
            $query->storefrontVisible();
        }

        return $query;
    }

    /**
     * Map incoming request parameters onto the model filter contract.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function normalizeFilters(array $params): array
    {
        $keyword = $params['keyword'] ?? $params['search'] ?? null;

        if (is_string($keyword)) {
            $keyword = trim($keyword);
            $keyword = $keyword === '' ? null : $keyword;
        }

        $filters = $params;
        $filters['search'] = $keyword;
        unset($filters['keyword'], $filters['sort'], $filters['per_page'], $filters['storefront_only']);

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }

    /**
     * Sort modes understood by this driver.
     *
     * @return list<string>
     */
    public function supportedSorts(): array
    {
        return array_map(fn (ProductSearchSort $sort): string => $sort->value, ProductSearchSort::cases());
    }
}
