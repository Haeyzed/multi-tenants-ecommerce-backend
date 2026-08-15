<?php

declare(strict_types=1);

namespace App\Services\Tenant\Product;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Enums\Tenant\Catalog\ProductVisibility;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\ProductVariant;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Tenant product catalog operations.
 */
class ProductService
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Paginate products with filters, brand, media, and variant counts.
     *
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     type?: string|null,
     *     visibility?: string|null,
     *     brand_id?: int|null,
     *     category_id?: int|null,
     *     is_featured?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Product>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Product::query()
            ->with(['brand', 'media'])
            ->withCount('variants')
            ->filter($params)
            ->when(
                array_key_exists('category_id', $params) && $params['category_id'] !== null,
                fn ($query) => $query->whereHas(
                    'categories',
                    fn ($query) => $query->where('categories.id', (int) $params['category_id']),
                ),
            )
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Product options for select inputs.
     *
     * @param  array{search?: string|null, status?: string|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Product::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $product): array => [
                'label' => $product->name,
                'value' => $product->id,
            ])
            ->values();
    }

    /**
     * Create a product with categories, price, images, and optional default variant.
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     * @param  list<int>  $categoryIds
     * @param  array<string, mixed>|null  $price
     */
    public function store(
        array $data,
        ?UploadedFile $image = null,
        array $images = [],
        array $categoryIds = [],
        ?array $price = null,
    ): Product {
        return DB::transaction(function () use ($data, $image, $images, $categoryIds, $price): Product {
            $sku = $data['sku'] ?? null;
            unset($data['sku'], $data['category_ids'], $data['attribute_value_ids'], $data['price']);

            $data['type'] ??= ProductType::Simple->value;
            $data['status'] ??= ProductStatus::Draft->value;
            $data['visibility'] ??= ProductVisibility::Public->value;

            /** @var Product $product */
            $product = Product::query()->create($data);

            $this->syncCategories($product, $categoryIds);

            if ($price !== null) {
                $this->createPrice($product, $price);
            }

            if ($image !== null) {
                $this->mediaService->add($product, $image, MediaCollection::Images);
            }

            if ($images !== []) {
                $this->mediaService->addMany($product, $images, MediaCollection::Images);
            }

            $type = $product->type instanceof ProductType
                ? $product->type
                : ProductType::tryFrom((string) $product->type) ?? ProductType::Simple;

            if ($type === ProductType::Simple && filled($sku)) {
                if (ProductVariant::query()->where('sku', $sku)->exists()) {
                    throw ValidationException::withMessages([
                        'sku' => 'SKU is already in use.',
                    ]);
                }

                ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $sku,
                    'unit_id' => $product->unit_id,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            }

            return $this->show($product);
        });
    }

    /**
     * Retrieve a product with common relations.
     */
    public function show(Product $product): Product
    {
        return $product->load([
            'brand',
            'unit',
            'categories',
            'variants',
            'attributeValues.attribute',
            'prices',
            'media',
        ]);
    }

    /**
     * Update a product with categories, price, images, and attribute values.
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     * @param  list<int>  $categoryIds
     * @param  list<int>|null  $attributeValueIds
     * @param  array<string, mixed>|null  $price
     */
    public function update(
        Product $product,
        array $data,
        ?UploadedFile $image = null,
        array $images = [],
        array $categoryIds = [],
        ?array $attributeValueIds = null,
        ?array $price = null,
    ): Product {
        return DB::transaction(function () use ($product, $data, $image, $images, $categoryIds, $attributeValueIds, $price): Product {
            unset($data['sku'], $data['category_ids'], $data['attribute_value_ids'], $data['price']);

            $product->fill($data);
            $product->save();

            if ($categoryIds !== []) {
                $this->syncCategories($product, $categoryIds);
            }

            if ($attributeValueIds !== null) {
                $this->attachAttributeValues($product, $attributeValueIds);
            }

            if ($price !== null) {
                $this->upsertActivePrice($product, $price);
            }

            if ($image !== null) {
                $this->mediaService->add($product, $image, MediaCollection::Images);
            }

            if ($images !== []) {
                $this->mediaService->addMany($product, $images, MediaCollection::Images);
            }

            return $this->show($product->fresh() ?? $product);
        });
    }

    /**
     * Delete a product when no stock remains on the product or its variants.
     *
     * @throws ValidationException
     */
    public function destroy(Product $product): void
    {
        if ($this->hasStock($product)) {
            throw ValidationException::withMessages([
                'product' => 'Cannot delete a product that has inventory with quantity greater than zero.',
            ]);
        }

        DB::transaction(function () use ($product): void {
            $this->mediaService->removeCollection($product, MediaCollection::Images);

            $product->load('variants');

            foreach ($product->variants as $variant) {
                $this->mediaService->removeCollection($variant, MediaCollection::Images);
            }

            $product->delete();
        });
    }

    /**
     * Sync category assignments for a product.
     *
     * @param  list<int>  $categoryIds
     */
    public function syncCategories(Product $product, array $categoryIds): void
    {
        $product->categories()->sync($categoryIds);
    }

    /**
     * Sync attribute value assignments for a product.
     *
     * @param  list<int>  $valueIds
     */
    public function attachAttributeValues(Product $product, array $valueIds): void
    {
        $product->attributeValues()->sync($valueIds);
    }

    /**
     * Attach one or more images to a product gallery.
     *
     * @param  list<UploadedFile>  $images
     */
    public function storeImage(Product $product, UploadedFile|array $images): Product
    {
        if ($images instanceof UploadedFile) {
            $this->mediaService->add($product, $images, MediaCollection::Images);
        } else {
            $this->mediaService->addMany($product, $images, MediaCollection::Images);
        }

        return $product->fresh(['media']) ?? $product->load('media');
    }

    /**
     * Remove product gallery images by media id.
     *
     * @param  list<int>  $mediaIds
     */
    public function destroyImages(Product $product, array $mediaIds): Product
    {
        $product->getMedia(MediaCollection::Images->value)
            ->filter(fn (Media $media): bool => in_array($media->id, $mediaIds, true))
            ->each(fn (Media $media) => $this->mediaService->remove($product, $media));

        return $product->fresh(['media']) ?? $product->load('media');
    }

    /**
     * @param  array<string, mixed>  $price
     */
    protected function createPrice(Product|ProductVariant $priceable, array $price): ProductPrice
    {
        return $priceable->prices()->create([
            'currency' => $price['currency'],
            'amount' => $price['amount'],
            'compare_at_amount' => $price['compare_at_amount'] ?? null,
            'cost_amount' => $price['cost_amount'] ?? null,
            'is_active' => $price['is_active'] ?? true,
            'starts_at' => $price['starts_at'] ?? null,
            'ends_at' => $price['ends_at'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $price
     */
    protected function upsertActivePrice(Product $product, array $price): ProductPrice
    {
        /** @var ProductPrice|null $existing */
        $existing = $product->prices()->where('is_active', true)->latest('id')->first();

        if ($existing !== null) {
            $existing->fill([
                'currency' => $price['currency'] ?? $existing->currency,
                'amount' => $price['amount'] ?? $existing->amount,
                'compare_at_amount' => $price['compare_at_amount'] ?? $existing->compare_at_amount,
                'cost_amount' => $price['cost_amount'] ?? $existing->cost_amount,
                'is_active' => $price['is_active'] ?? $existing->is_active,
                'starts_at' => $price['starts_at'] ?? $existing->starts_at,
                'ends_at' => $price['ends_at'] ?? $existing->ends_at,
            ]);
            $existing->save();

            return $existing;
        }

        return $this->createPrice($product, $price);
    }

    protected function hasStock(Product $product): bool
    {
        if ($product->inventories()->where('quantity', '>', 0)->exists()) {
            return true;
        }

        return ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereHas('inventories', fn ($query) => $query->where('quantity', '>', 0))
            ->exists();
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
