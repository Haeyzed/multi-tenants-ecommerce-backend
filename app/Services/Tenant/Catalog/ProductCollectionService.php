<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Catalog\CollectionStatus;
use App\Enums\Tenant\Catalog\CollectionType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCollection;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant product collection catalog operations.
 */
class ProductCollectionService
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Paginate collections with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     type?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, ProductCollection>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return ProductCollection::query()
            ->with('media')
            ->withCount('products')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a collection with media and products.
     */
    public function show(ProductCollection $collection): ProductCollection
    {
        return $collection->load(['media', 'products.media', 'seo']);
    }

    /**
     * Create a collection and optionally attach an image.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, ?UploadedFile $image = null): ProductCollection
    {
        return DB::transaction(function () use ($data, $image): ProductCollection {
            $data['type'] ??= CollectionType::Manual->value;
            $data['status'] ??= CollectionStatus::Draft->value;

            /** @var ProductCollection $collection */
            $collection = ProductCollection::query()->create($data);

            if ($image !== null) {
                $this->mediaService->replace($collection, $image, MediaCollection::Image);
            }

            return $this->show($collection);
        });
    }

    /**
     * Update a collection and optionally replace its image.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ProductCollection $collection, array $data, ?UploadedFile $image = null): ProductCollection
    {
        return DB::transaction(function () use ($collection, $data, $image): ProductCollection {
            $collection->fill($data);
            $collection->save();

            if ($image !== null) {
                $this->mediaService->replace($collection, $image, MediaCollection::Image);
            }

            return $this->show($collection->fresh() ?? $collection);
        });
    }

    /**
     * Delete a collection and its image.
     */
    public function destroy(ProductCollection $collection): void
    {
        DB::transaction(function () use ($collection): void {
            $this->mediaService->removeCollection($collection, MediaCollection::Image);
            $collection->delete();
        });
    }

    /**
     * Replace-set collection products.
     *
     * @param  list<array{product_id: int, sort_order?: int}>  $items
     *
     * @throws ValidationException
     */
    public function syncProducts(ProductCollection $collection, array $items): ProductCollection
    {
        $productIds = array_map(fn (array $item): int => (int) $item['product_id'], $items);

        if (count($productIds) !== count(array_unique($productIds))) {
            throw ValidationException::withMessages([
                'products' => 'Product ids must be unique.',
            ]);
        }

        if ($productIds !== []) {
            $existingCount = Product::query()->whereIn('id', $productIds)->count();

            if ($existingCount !== count($productIds)) {
                throw ValidationException::withMessages([
                    'products' => 'One or more products do not exist.',
                ]);
            }
        }

        $sync = [];

        foreach ($items as $index => $item) {
            $sync[(int) $item['product_id']] = [
                'sort_order' => (int) ($item['sort_order'] ?? $index),
            ];
        }

        $collection->products()->sync($sync);

        return $this->show($collection->fresh() ?? $collection);
    }

    /**
     * Attach a product to a collection.
     */
    public function attach(ProductCollection $collection, int $productId, int $sortOrder = 0): ProductCollection
    {
        if (! Product::query()->whereKey($productId)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'Product does not exist.',
            ]);
        }

        $collection->products()->syncWithoutDetaching([
            $productId => ['sort_order' => $sortOrder],
        ]);

        return $this->show($collection->fresh() ?? $collection);
    }

    /**
     * Detach a product from a collection.
     */
    public function detach(ProductCollection $collection, int $productId): ProductCollection
    {
        $collection->products()->detach($productId);

        return $this->show($collection->fresh() ?? $collection);
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
