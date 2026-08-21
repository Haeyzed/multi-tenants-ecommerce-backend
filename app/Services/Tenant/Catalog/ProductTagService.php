<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductTag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Tenant product tag catalog operations.
 */
class ProductTagService
{
    /**
     * Paginate tags with search and filters.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, ProductTag>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return ProductTag::query()
            ->withCount('products')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Tag options for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return ProductTag::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductTag $tag): array => [
                'label' => $tag->name,
                'value' => $tag->id,
            ])
            ->values();
    }

    /**
     * Retrieve a tag.
     *
     * @param  ProductTag  $tag
     * @return ProductTag
     */
    public function show(ProductTag $tag): ProductTag
    {
        return $tag->loadCount('products');
    }

    /**
     * Create a tag.
     *
     * @param  array{name: string, slug?: string|null, is_active?: bool}  $data
     * @return ProductTag
     */
    public function store(array $data): ProductTag
    {
        /** @var ProductTag $tag */
        $tag = ProductTag::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->show($tag);
    }

    /**
     * Update a tag.
     *
     * @param  ProductTag  $tag
     * @param  array{name?: string, slug?: string|null, is_active?: bool}  $data
     * @return ProductTag
     */
    public function update(ProductTag $tag, array $data): ProductTag
    {
        $tag->fill($data);
        $tag->save();

        return $this->show($tag->fresh() ?? $tag);
    }

    /**
     * Delete a tag.
     *
     * @param  ProductTag  $tag
     * @return void
     */
    public function destroy(ProductTag $tag): void
    {
        $tag->delete();
    }

    /**
     * Replace-set tags on a product.
     *
     * @param  Product  $product
     * @param  list<int>  $tagIds
     * @return Product
     *
     * @throws ValidationException
     */
    public function syncToProduct(Product $product, array $tagIds): Product
    {
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));

        if ($tagIds !== []) {
            $existingCount = ProductTag::query()->whereIn('id', $tagIds)->count();

            if ($existingCount !== count($tagIds)) {
                throw ValidationException::withMessages([
                    'tag_ids' => 'One or more tags do not exist.',
                ]);
            }
        }

        $product->tags()->sync($tagIds);

        return $product->load('tags');
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
