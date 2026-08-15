<?php

declare(strict_types=1);

namespace App\Services\Tenant\Product;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Catalog\ProductType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\ProductVariant;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant product variant catalog operations.
 */
class ProductVariantService
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Paginate variants for a product.
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, ProductVariant>
     */
    public function listForProduct(Product $product, array $params = []): LengthAwarePaginator
    {
        $params['product_id'] = $product->id;

        return ProductVariant::query()
            ->with(['optionValues.option', 'prices', 'media'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Create a variant with option values and optional price/image.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $optionValueIds
     * @param  array<string, mixed>|null  $price
     *
     * @throws ValidationException
     */
    public function store(
        Product $product,
        array $data,
        array $optionValueIds = [],
        ?array $price = null,
        ?UploadedFile $image = null,
    ): ProductVariant {
        $this->assertProductAllowsVariants($product);
        $this->assertUniqueSku($data['sku'] ?? null, null);
        $this->assertUniqueBarcode($data['barcode'] ?? null, null);
        $this->assertUniqueOptionCombination($product, $optionValueIds);

        return DB::transaction(function () use ($product, $data, $optionValueIds, $price, $image): ProductVariant {
            unset($data['option_value_ids'], $data['price']);

            /** @var ProductVariant $variant */
            $variant = $product->variants()->create([
                ...$data,
                'product_id' => $product->id,
            ]);

            if ($optionValueIds !== []) {
                $variant->optionValues()->sync($optionValueIds);
            }

            if ($price !== null) {
                $variant->prices()->create([
                    'currency' => $price['currency'],
                    'amount' => $price['amount'],
                    'compare_at_amount' => $price['compare_at_amount'] ?? null,
                    'cost_amount' => $price['cost_amount'] ?? null,
                    'is_active' => $price['is_active'] ?? true,
                    'starts_at' => $price['starts_at'] ?? null,
                    'ends_at' => $price['ends_at'] ?? null,
                ]);
            }

            if ($image !== null) {
                $this->mediaService->replace($variant, $image, MediaCollection::Images);
            }

            return $this->show($variant);
        });
    }

    /**
     * Retrieve a variant with relations.
     */
    public function show(ProductVariant $variant): ProductVariant
    {
        return $variant->load([
            'product',
            'unit',
            'optionValues.option',
            'prices',
            'media',
            'inventories.warehouse',
        ]);
    }

    /**
     * Update a variant with option values and optional price/image.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $optionValueIds
     * @param  array<string, mixed>|null  $price
     *
     * @throws ValidationException
     */
    public function update(
        ProductVariant $variant,
        array $data,
        ?array $optionValueIds = null,
        ?array $price = null,
        ?UploadedFile $image = null,
    ): ProductVariant {
        if (array_key_exists('sku', $data)) {
            $this->assertUniqueSku($data['sku'], $variant->id);
        }

        if (array_key_exists('barcode', $data) && filled($data['barcode'])) {
            $this->assertUniqueBarcode($data['barcode'], $variant->id);
        }

        if ($optionValueIds !== null) {
            $this->assertUniqueOptionCombination($variant->product, $optionValueIds, $variant->id);
        }

        return DB::transaction(function () use ($variant, $data, $optionValueIds, $price, $image): ProductVariant {
            unset($data['option_value_ids'], $data['price']);

            $variant->fill($data);
            $variant->save();

            if ($optionValueIds !== null) {
                $variant->optionValues()->sync($optionValueIds);
            }

            if ($price !== null) {
                $this->upsertActivePrice($variant, $price);
            }

            if ($image !== null) {
                $this->mediaService->replace($variant, $image, MediaCollection::Images);
            }

            return $this->show($variant->fresh() ?? $variant);
        });
    }

    /**
     * Delete a variant when no stock remains.
     *
     * @throws ValidationException
     */
    public function destroy(ProductVariant $variant): void
    {
        if ($variant->inventories()->where('quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'variant' => 'Cannot delete a variant that has inventory with quantity greater than zero.',
            ]);
        }

        DB::transaction(function () use ($variant): void {
            $this->mediaService->removeCollection($variant, MediaCollection::Images);
            $variant->delete();
        });
    }

    /**
     * Attach or replace a variant image.
     */
    public function storeImage(ProductVariant $variant, UploadedFile $image): ProductVariant
    {
        $this->mediaService->replace($variant, $image, MediaCollection::Images);

        return $variant->fresh(['media']) ?? $variant->load('media');
    }

    /**
     * Remove a variant image.
     */
    public function destroyImage(ProductVariant $variant): ProductVariant
    {
        $this->mediaService->removeCollection($variant, MediaCollection::Images);

        return $variant->fresh(['media']) ?? $variant->load('media');
    }

    /**
     * @throws ValidationException
     */
    protected function assertProductAllowsVariants(Product $product): void
    {
        $type = $product->type instanceof ProductType ? $product->type : ProductType::from((string) $product->type);

        if (! $product->has_variants && $type !== ProductType::Variable) {
            throw ValidationException::withMessages([
                'product' => 'This product does not support variants.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertUniqueSku(?string $sku, ?int $ignoreVariantId): void
    {
        if (! filled($sku)) {
            throw ValidationException::withMessages([
                'sku' => 'SKU is required for a variant.',
            ]);
        }

        $query = ProductVariant::query()->where('sku', $sku);

        if ($ignoreVariantId !== null) {
            $query->whereKeyNot($ignoreVariantId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'sku' => 'SKU is already in use.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertUniqueBarcode(?string $barcode, ?int $ignoreVariantId): void
    {
        if (! filled($barcode)) {
            return;
        }

        $query = ProductVariant::query()->where('barcode', $barcode);

        if ($ignoreVariantId !== null) {
            $query->whereKeyNot($ignoreVariantId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode is already in use.',
            ]);
        }
    }

    /**
     * @param  list<int>  $optionValueIds
     *
     * @throws ValidationException
     */
    protected function assertUniqueOptionCombination(
        Product $product,
        array $optionValueIds,
        ?int $ignoreVariantId = null,
    ): void {
        if ($optionValueIds === []) {
            return;
        }

        sort($optionValueIds);

        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->when($ignoreVariantId !== null, fn ($query) => $query->whereKeyNot($ignoreVariantId))
            ->with('optionValues:id')
            ->get();

        foreach ($variants as $variant) {
            $existing = $variant->optionValues->pluck('id')->sort()->values()->all();

            if ($existing === $optionValueIds) {
                throw ValidationException::withMessages([
                    'option_value_ids' => 'A variant with this option combination already exists.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $price
     */
    protected function upsertActivePrice(ProductVariant $variant, array $price): ProductPrice
    {
        /** @var ProductPrice|null $existing */
        $existing = $variant->prices()->where('is_active', true)->latest('id')->first();

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

        return $variant->prices()->create([
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
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
