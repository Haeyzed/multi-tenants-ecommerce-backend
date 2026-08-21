<?php

declare(strict_types=1);

namespace App\Services\Tenant\Inventory;

use App\Enums\Tenant\Catalog\ProductType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the catalogue record that owns warehouse inventory.
 *
 * Simple products may have an implicit SKU variant. Variable products always
 * stock at the variant. Inventory is never keyed by warehouse on the product.
 */
class InventoryStockableResolver
{
    /**
     * Resolve.
     *
     * @param  Product  $product
     * @param  ?ProductVariant  $variant
     * @return Product|ProductVariant
     *
     * @throws ValidationException
     */
    public function resolve(Product $product, ?ProductVariant $variant = null): Product|ProductVariant
    {
        if ($variant !== null) {
            if ((int) $variant->product_id !== (int) $product->id) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The selected variant does not belong to this product.',
                ]);
            }

            return $variant;
        }

        if ($this->requiresVariant($product)) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'A product variant is required for this product.',
            ]);
        }

        return $this->implicitVariant($product) ?? $product;
    }

    /**
     * Catalogue records that may hold warehouse stock for this product/variant pair.
     *
     * @param  Product  $product
     * @param  ?ProductVariant  $variant
     * @return list<Product|ProductVariant>
     *
     * @throws ValidationException
     */
    public function stockHolders(Product $product, ?ProductVariant $variant = null): array
    {
        if ($variant !== null) {
            return [$this->resolve($product, $variant)];
        }

        if ($this->requiresVariant($product)) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'A product variant is required for this product.',
            ]);
        }

        $holders = [$product];
        $implicit = $this->implicitVariant($product);

        if ($implicit !== null) {
            $holders[] = $implicit;
        }

        return $holders;
    }

    /**
     * Limit an inventory query to the catalogue records that can hold this stock.
     *
     * @param  Builder  $query
     * @param  Product  $product
     * @param  ?ProductVariant  $variant
     * @return void
     *
     * @throws ValidationException
     */
    public function constrainInventoryQuery(Builder $query, Product $product, ?ProductVariant $variant = null): void
    {
        $holders = $this->stockHolders($product, $variant);

        $query->where(function (Builder $outer) use ($holders): void {
            foreach ($holders as $holder) {
                $outer->orWhere(function (Builder $inner) use ($holder): void {
                    $inner->where('inventoryable_type', $holder->getMorphClass())
                        ->where('inventoryable_id', $holder->getKey());
                });
            }
        });
    }

    /**
     * Requires variant.
     *
     * @param  Product  $product
     * @return bool
     */
    public function requiresVariant(Product $product): bool
    {
        return $product->has_variants || $product->type === ProductType::Variable;
    }

    /**
     * Implicit variant.
     *
     * @param  Product  $product
     * @return ?ProductVariant
     */
    protected function implicitVariant(Product $product): ?ProductVariant
    {
        if ($this->requiresVariant($product)) {
            return null;
        }

        $variant = $product->relationLoaded('variants')
            ? $product->variants->sortBy('id')->first()
            : $product->variants()->orderBy('id')->first();

        return $variant instanceof ProductVariant ? $variant : null;
    }
}
