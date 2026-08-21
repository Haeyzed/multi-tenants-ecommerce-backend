<?php

declare(strict_types=1);

namespace App\Services\Tenant\Product;

use App\Enums\Tenant\Catalog\ProductType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundleItem;
use App\Models\Tenant\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bundle product line-item sync operations.
 */
class ProductBundleService
{
    /**
     * List bundle items with product and variant.
     *
     * @param  Product  $bundle
     * @return Collection<int, ProductBundleItem>
     */
    public function listItems(Product $bundle): Collection
    {
        return $bundle->bundleItems()->with(['product.media', 'variant'])->get();
    }

    /**
     * Replace-set bundle items.
     *
     * @param  Product  $bundle
     * @param  list<array{product_id: int, product_variant_id?: int|null, quantity?: int, sort_order?: int}>  $items
     * @return Collection<int, ProductBundleItem>
     *
     * @throws ValidationException
     */
    public function syncItems(Product $bundle, array $items): Collection
    {
        if ($bundle->type !== ProductType::Bundle) {
            throw ValidationException::withMessages([
                'product' => 'Only bundle products can have bundle items.',
            ]);
        }

        $productIds = array_map(fn (array $item): int => (int) $item['product_id'], $items);

        if (in_array($bundle->id, $productIds, true)) {
            throw ValidationException::withMessages([
                'items' => 'A bundle cannot include itself.',
            ]);
        }

        if ($productIds !== []) {
            $products = Product::query()->whereIn('id', $productIds)->get(['id', 'type']);

            if ($products->count() !== count(array_unique($productIds))) {
                throw ValidationException::withMessages([
                    'items' => 'One or more bundle products do not exist.',
                ]);
            }

            if ($products->contains(fn (Product $product): bool => $product->type === ProductType::Bundle)) {
                throw ValidationException::withMessages([
                    'items' => 'Nested bundle products are not allowed.',
                ]);
            }
        }

        foreach ($items as $index => $item) {
            $variantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;

            if ($variantId === null) {
                continue;
            }

            $variantBelongs = ProductVariant::query()
                ->whereKey($variantId)
                ->where('product_id', (int) $item['product_id'])
                ->exists();

            if (! $variantBelongs) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Variant does not belong to the selected product.',
                ]);
            }
        }

        return DB::transaction(function () use ($bundle, $items): Collection {
            $bundle->bundleItems()->delete();

            foreach ($items as $index => $item) {
                $bundle->bundleItems()->create([
                    'product_id' => (int) $item['product_id'],
                    'product_variant_id' => isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                ]);
            }

            return $this->listItems($bundle);
        });
    }
}
