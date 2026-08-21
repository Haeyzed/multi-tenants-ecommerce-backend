<?php

declare(strict_types=1);

namespace App\Services\Tenant\Product;

use App\Enums\Tenant\Catalog\ProductAvailability;
use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Enums\Tenant\Catalog\ProductVisibility;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundleItem;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Inventory\InventoryStockableResolver;
use Illuminate\Support\Carbon;

/**
 * Computes safe sellable availability for products and variants.
 */
class ProductAvailabilityService
{
    /**
     * Create a new class instance.
     *
     * @param  InventoryStockableResolver  $stockables
     */
    public function __construct(private readonly InventoryStockableResolver $stockables) {}

    /**
     * Resolve availability for a catalog product (including bundles).
     *
     * @param  Product  $product
     * @return ProductAvailability
     */
    public function forProduct(Product $product): ProductAvailability
    {
        if (! $this->isProductSellable($product)) {
            return ProductAvailability::Unavailable;
        }

        if ($product->type === ProductType::Bundle) {
            return $this->forBundle($product);
        }

        if ($product->is_preorder && $this->isWithinPreorderWindow($product->preorder_start_at, $product->preorder_end_at)) {
            return ProductAvailability::Preorder;
        }

        if ($product->has_variants || $product->type === ProductType::Variable) {
            return $this->forVariableProduct($product);
        }

        return $this->fromStockable(
            availableQty: $this->sumAvailableForProduct($product),
            lowStockThreshold: $this->productLowStockThreshold($product),
            allowBackorder: (bool) $product->allow_backorder,
            isPreorder: (bool) $product->is_preorder,
            preorderStart: $product->preorder_start_at,
            preorderEnd: $product->preorder_end_at,
        );
    }

    /**
     * Resolve availability for a single variant.
     *
     * @param  ProductVariant  $variant
     * @param  ?Product  $product
     * @return ProductAvailability
     */
    public function forVariant(ProductVariant $variant, ?Product $product = null): ProductAvailability
    {
        $product ??= $variant->product;

        if ($product === null || ! $this->isProductSellable($product) || ! $variant->is_active) {
            return ProductAvailability::Unavailable;
        }

        $allowBackorder = $variant->allow_backorder ?? $product->allow_backorder;
        $isPreorder = $variant->is_preorder ?? $product->is_preorder;
        $preorderStart = $variant->preorder_start_at ?? $product->preorder_start_at;
        $preorderEnd = $variant->preorder_end_at ?? $product->preorder_end_at;

        if ($isPreorder && $this->isWithinPreorderWindow($preorderStart, $preorderEnd)) {
            return ProductAvailability::Preorder;
        }

        return $this->fromStockable(
            availableQty: $this->sumAvailable($variant),
            lowStockThreshold: $variant->low_stock_threshold ?? $this->productLowStockThreshold($product),
            allowBackorder: (bool) $allowBackorder,
            isPreorder: (bool) $isPreorder,
            preorderStart: $preorderStart,
            preorderEnd: $preorderEnd,
        );
    }

    /**
     * Map stock quantity and flags to an availability state.
     *
     * @param  int  $availableQty
     * @param  ?int  $lowStockThreshold
     * @param  bool  $allowBackorder
     * @param  bool  $isPreorder
     * @param  ?Carbon  $preorderStart
     * @param  ?Carbon  $preorderEnd
     * @return ProductAvailability
     */
    public function fromStockable(
        int $availableQty,
        ?int $lowStockThreshold,
        bool $allowBackorder,
        bool $isPreorder = false,
        ?Carbon $preorderStart = null,
        ?Carbon $preorderEnd = null,
    ): ProductAvailability {
        if ($isPreorder && $this->isWithinPreorderWindow($preorderStart, $preorderEnd)) {
            return ProductAvailability::Preorder;
        }

        if ($availableQty > 0) {
            if ($lowStockThreshold !== null && $availableQty <= $lowStockThreshold) {
                return ProductAvailability::LowStock;
            }

            return ProductAvailability::InStock;
        }

        if ($allowBackorder) {
            return ProductAvailability::Backorder;
        }

        return ProductAvailability::OutOfStock;
    }

    /**
     * Whether the product is visible on the public storefront.
     *
     * @param  Product  $product
     * @return bool
     */
    public function isProductSellable(Product $product): bool
    {
        if ($product->status !== ProductStatus::Active) {
            return false;
        }

        if ($product->visibility !== ProductVisibility::Public) {
            return false;
        }

        $now = now();

        if ($product->published_at !== null && $product->published_at->isFuture()) {
            return false;
        }

        if ($product->unpublished_at !== null && $product->unpublished_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Bundle availability is limited by the weakest component.
     *
     * @param  Product  $bundle
     * @return ProductAvailability
     */
    protected function forBundle(Product $bundle): ProductAvailability
    {
        $items = $bundle->relationLoaded('bundleItems')
            ? $bundle->bundleItems
            : $bundle->bundleItems()->with(['product', 'variant'])->get();

        if ($items->isEmpty()) {
            return ProductAvailability::Unavailable;
        }

        $states = [];

        foreach ($items as $item) {
            /** @var ProductBundleItem $item */
            $states[] = $this->forBundleItem($item);
        }

        $priority = [
            ProductAvailability::Unavailable->value => 0,
            ProductAvailability::OutOfStock->value => 1,
            ProductAvailability::Backorder->value => 2,
            ProductAvailability::Preorder->value => 3,
            ProductAvailability::LowStock->value => 4,
            ProductAvailability::InStock->value => 5,
        ];

        usort($states, fn (ProductAvailability $a, ProductAvailability $b): int => ($priority[$a->value] ?? 0) <=> ($priority[$b->value] ?? 0));

        return $states[0];
    }

    /**
     * For bundle item.
     *
     * @param  ProductBundleItem  $item
     * @return ProductAvailability
     */
    protected function forBundleItem(ProductBundleItem $item): ProductAvailability
    {
        if ($item->variant !== null) {
            return $this->forVariant($item->variant, $item->product);
        }

        if ($item->product === null) {
            return ProductAvailability::Unavailable;
        }

        return $this->forProduct($item->product);
    }

    /**
     * For variable product.
     *
     * @param  Product  $product
     * @return ProductAvailability
     */
    protected function forVariableProduct(Product $product): ProductAvailability
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants->where('is_active', true)
            : $product->variants()->where('is_active', true)->get();

        if ($variants->isEmpty()) {
            return ProductAvailability::OutOfStock;
        }

        $states = $variants->map(fn (ProductVariant $variant): ProductAvailability => $this->forVariant($variant, $product));

        if ($states->contains(ProductAvailability::InStock)) {
            return ProductAvailability::InStock;
        }

        if ($states->contains(ProductAvailability::LowStock)) {
            return ProductAvailability::LowStock;
        }

        if ($states->contains(ProductAvailability::Preorder)) {
            return ProductAvailability::Preorder;
        }

        if ($states->contains(ProductAvailability::Backorder)) {
            return ProductAvailability::Backorder;
        }

        return ProductAvailability::OutOfStock;
    }

    /**
     * Sum available quantity across all warehouse inventory rows.
     *
     * @param  Product|ProductVariant  $stockable
     * @return int
     */
    protected function sumAvailable(Product|ProductVariant $stockable): int
    {
        $inventories = $stockable->relationLoaded('inventories')
            ? $stockable->inventories
            : $stockable->inventories()->get();

        return (int) $inventories->sum(fn (Inventory $inventory): int => $inventory->availableQuantity());
    }

    /**
     * Sum available for product.
     *
     * @param  Product  $product
     * @return int
     */
    protected function sumAvailableForProduct(Product $product): int
    {
        $total = 0;

        foreach ($this->stockables->stockHolders($product) as $holder) {
            $total += $this->sumAvailable($holder);
        }

        return $total;
    }

    /**
     * Product low stock threshold.
     *
     * @param  Product  $product
     * @return ?int
     */
    protected function productLowStockThreshold(Product $product): ?int
    {
        $levels = collect();

        foreach ($this->stockables->stockHolders($product) as $holder) {
            $inventories = $holder->relationLoaded('inventories')
                ? $holder->inventories
                : $holder->inventories()->get();

            $levels = $levels->concat($inventories->pluck('reorder_level'));
        }

        $levels = $levels->filter(fn ($level): bool => $level !== null);

        return $levels->isEmpty() ? null : (int) $levels->min();
    }

    /**
     * Is within preorder window.
     *
     * @param  ?Carbon  $start
     * @param  ?Carbon  $end
     * @return bool
     */
    protected function isWithinPreorderWindow(?Carbon $start, ?Carbon $end): bool
    {
        $now = now();

        if ($start !== null && $start->isFuture()) {
            return false;
        }

        if ($end !== null && $end->isPast()) {
            return false;
        }

        return true;
    }
}
