<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Catalog\ProductAvailability;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Wishlist;
use App\Models\Tenant\WishlistItem;
use App\Services\Tenant\Product\ProductAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Customer wishlist operations.
 */
class WishlistService
{
    public function __construct(
        private readonly BackInStockNotificationService $backInStock,
        private readonly CommerceAnalyticsService $analytics,
        private readonly ProductAvailabilityService $availability,
    ) {}

    /**
     * Get or create the customer's wishlist with items loaded.
     */
    public function getWishlist(Customer $customer): Wishlist
    {
        $wishlist = $this->getOrCreateWishlist($customer);

        return $wishlist->load([
            'items.product.prices',
            'items.variant.prices',
        ]);
    }

    /**
     * Add a product (and optional variant) to the wishlist.
     *
     * @throws ValidationException
     */
    public function addItem(
        Customer $customer,
        int $productId,
        ?int $variantId = null,
    ): WishlistItem {
        $product = Product::query()->find($productId);

        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }

        if ($variantId !== null) {
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereKey($variantId)
                ->first();

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The selected variant does not belong to this product.',
                ]);
            }
        }

        $wishlist = $this->getOrCreateWishlist($customer);

        /** @var WishlistItem $item */
        $item = DB::transaction(function () use ($wishlist, $product, $variantId): WishlistItem {
            /** @var WishlistItem $item */
            $item = WishlistItem::query()->firstOrCreate(
                [
                    'wishlist_id' => $wishlist->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                ],
            );

            return $item;
        });

        $availability = $variantId !== null
            ? $this->availability->forVariant(ProductVariant::query()->findOrFail($variantId))
            : $this->availability->forProduct($product);

        if (in_array($availability, [ProductAvailability::OutOfStock, ProductAvailability::Unavailable], true)) {
            $this->backInStock->subscribe($customer->id, $product->id, $variantId);
        }

        $this->analytics->record('wishlist.item_added', $item, $customer, [
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
        ]);

        return $item->load(['product.prices', 'variant.prices']);
    }

    /**
     * Remove an item from the customer's wishlist.
     */
    public function removeItem(Customer $customer, WishlistItem $item): void
    {
        $wishlist = $this->getOrCreateWishlist($customer);

        if ((int) $item->wishlist_id !== (int) $wishlist->id) {
            throw new AccessDeniedHttpException('Wishlist item does not belong to this customer.');
        }

        $item->delete();
    }

    /**
     * Check whether a product is in the customer's wishlist.
     *
     * @return array{in_wishlist: bool, wishlist_item_id: int|null}
     */
    public function check(Customer $customer, Product $product, ?int $variantId = null): array
    {
        $wishlist = $this->getOrCreateWishlist($customer);

        $query = WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('product_id', $product->id);

        if ($variantId !== null) {
            $query->where('product_variant_id', $variantId);
        }

        $item = $query->first();

        return [
            'in_wishlist' => $item !== null,
            'wishlist_item_id' => $item?->id,
        ];
    }

    protected function getOrCreateWishlist(Customer $customer): Wishlist
    {
        return Wishlist::query()->firstOrCreate([
            'customer_id' => $customer->id,
        ]);
    }
}
