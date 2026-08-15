<?php

declare(strict_types=1);

namespace App\Services\Tenant\Product;

use App\Enums\Tenant\Catalog\ProductRelationType;
use App\Models\Tenant\Product;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Typed product relation sync (related / upsell / cross-sell).
 */
class ProductRelationService
{
    /**
     * List related products for a relation type with pivot data.
     *
     * @return Collection<int, Product>
     */
    public function list(Product $product, ProductRelationType $type): Collection
    {
        return $this->relationQuery($product, $type)
            ->with(['media', 'brand'])
            ->get();
    }

    /**
     * Replace-set related products for a relation type.
     *
     * @param  list<array{product_id: int, sort_order?: int}>  $items
     * @return Collection<int, Product>
     *
     * @throws ValidationException
     */
    public function sync(Product $product, ProductRelationType $type, array $items): Collection
    {
        $relatedIds = array_map(fn (array $item): int => (int) $item['product_id'], $items);

        if (in_array($product->id, $relatedIds, true)) {
            throw ValidationException::withMessages([
                'items' => 'A product cannot be related to itself.',
            ]);
        }

        if (count($relatedIds) !== count(array_unique($relatedIds))) {
            throw ValidationException::withMessages([
                'items' => 'Related product ids must be unique.',
            ]);
        }

        if ($relatedIds !== []) {
            $existingCount = Product::query()->whereIn('id', $relatedIds)->count();

            if ($existingCount !== count($relatedIds)) {
                throw ValidationException::withMessages([
                    'items' => 'One or more related products do not exist.',
                ]);
            }
        }

        DB::transaction(function () use ($product, $type, $items): void {
            $product->productRelations()->where('type', $type->value)->delete();

            foreach ($items as $index => $item) {
                $product->productRelations()->create([
                    'related_product_id' => (int) $item['product_id'],
                    'type' => $type,
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                ]);
            }
        });

        return $this->list($product, $type);
    }

    /**
     * @return BelongsToMany<Product, Product>
     */
    protected function relationQuery(Product $product, ProductRelationType $type)
    {
        return match ($type) {
            ProductRelationType::Related => $product->relatedProducts(),
            ProductRelationType::Upsell => $product->upsells(),
            ProductRelationType::CrossSell => $product->crossSells(),
        };
    }
}
