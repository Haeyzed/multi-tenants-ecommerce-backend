<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Marketplace\SellerOfferStatus;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerOffer;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Support\Money;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Seller commercial offers with inventory integration.
 */
class SellerOfferService
{
    /**
     * Create a new class instance.
     *
     * @param  InventoryService  $inventoryService
     * @param  CommerceSettingService  $commerceSettings
     */
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly CommerceSettingService $commerceSettings,
    ) {}

    /**
     * seller_id?: int|null, product_id?: int|null, status?: string|null, search?: string|null, per_page?: int|null }  $params
     *
     * @param  array{
     *     seller_id?: int|null,
     *     product_id?: int|null,
     *     status?: string|null,
     *     search?: string|null,
     *     per_page?: int|null
     * }  $params
     * @param  ?Authenticatable  $actor
     * @return LengthAwarePaginator<int, SellerOffer>
     */
    public function list(array $params = [], ?Authenticatable $actor = null): LengthAwarePaginator
    {
        $query = SellerOffer::query()
            ->with(['seller', 'product', 'productVariant'])
            ->latest('id');

        if ($actor instanceof Seller) {
            $query->where('seller_id', $actor->id);
        } elseif (! empty($params['seller_id'])) {
            $query->where('seller_id', $params['seller_id']);
        }

        if (! empty($params['product_id'])) {
            $query->where('product_id', $params['product_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['search'])) {
            $search = (string) $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('sku', 'like', "%{$search}%");
            });
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * Create a resource.
     *
     * @param  array<string, mixed>  $data
     * @param  ?Authenticatable  $actor
     * @return SellerOffer
     *
     * @throws ValidationException
     */
    public function store(array $data, ?Authenticatable $actor = null): SellerOffer
    {
        if ($actor instanceof Seller) {
            $data['seller_id'] = $actor->id;
        }

        $seller = $this->resolveSeller((int) $data['seller_id'], $actor);
        $this->assertSellerCanManageOffers($seller);

        $product = Product::query()->findOrFail((int) $data['product_id']);
        $this->assertProductSellable($product);

        $variantId = isset($data['product_variant_id']) ? (int) $data['product_variant_id'] : null;
        if ($variantId !== null) {
            $this->assertVariantBelongsToProduct($product, $variantId);
        }

        $currency = strtoupper((string) ($data['currency'] ?? $this->commerceSettings->currencyCode()));
        $price = Money::add((string) $data['price'], '0');

        $offer = SellerOffer::query()->create([
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'sku' => $data['sku'] ?? null,
            'currency' => $currency,
            'price' => $price,
            'compare_at_price' => isset($data['compare_at_price'])
                ? Money::add((string) $data['compare_at_price'], '0')
                : null,
            'cost' => isset($data['cost']) ? Money::add((string) $data['cost'], '0') : null,
            'status' => $data['status'] ?? SellerOfferStatus::Active->value,
            'metadata' => $data['metadata'] ?? null,
        ]);

        if (isset($data['stock']) && (int) $data['stock'] > 0) {
            $this->setStock($offer, (int) $data['stock']);
        }

        return $offer->load(['seller', 'product', 'productVariant']);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  SellerOffer  $offer
     * @return SellerOffer
     */
    public function show(SellerOffer $offer): SellerOffer
    {
        return $offer->load(['seller', 'product', 'productVariant', 'inventories.warehouse']);
    }

    /**
     * Update a resource.
     *
     * @param  SellerOffer  $offer
     * @param  array<string, mixed>  $data
     * @param  ?Authenticatable  $actor
     * @return SellerOffer
     *
     * @throws ValidationException
     */
    public function update(SellerOffer $offer, array $data, ?Authenticatable $actor = null): SellerOffer
    {
        $this->assertActorOwnsOffer($offer, $actor);

        if (isset($data['price'])) {
            $data['price'] = Money::add((string) $data['price'], '0');
        }
        if (array_key_exists('compare_at_price', $data) && $data['compare_at_price'] !== null) {
            $data['compare_at_price'] = Money::add((string) $data['compare_at_price'], '0');
        }
        if (array_key_exists('cost', $data) && $data['cost'] !== null) {
            $data['cost'] = Money::add((string) $data['cost'], '0');
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        unset($data['seller_id'], $data['product_id'], $data['product_variant_id']);

        $offer->fill($data);
        $offer->save();

        if (isset($data['stock'])) {
            $this->setStock($offer, (int) $data['stock']);
        }

        return $offer->fresh(['seller', 'product', 'productVariant']) ?? $offer;
    }

    /**
     * Delete a resource.
     *
     * @param  SellerOffer  $offer
     * @param  ?Authenticatable  $actor
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(SellerOffer $offer, ?Authenticatable $actor = null): void
    {
        $this->assertActorOwnsOffer($offer, $actor);
        $offer->delete();
    }

    /**
     * Set absolute on-hand stock for the offer at the default warehouse.
     *
     * @param  SellerOffer  $offer
     * @param  int  $quantity
     * @return void
     *
     * @throws ValidationException
     */
    public function setStock(SellerOffer $offer, int $quantity): void
    {
        if ($quantity < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Stock cannot be negative.',
            ]);
        }

        $warehouse = Warehouse::query()->where('is_default', true)->first()
            ?? Warehouse::query()->orderBy('id')->first();

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'stock' => 'No warehouse is configured.',
            ]);
        }

        $inventory = $this->inventoryService->getOrCreate($warehouse, $offer);
        $delta = $quantity - (int) $inventory->quantity;

        if ($delta !== 0) {
            $this->inventoryService->adjust(
                $inventory,
                $delta,
                InventoryMovementType::Adjustment,
                'Seller offer stock sync',
            );
        }

        if ($quantity === 0 && $offer->status === SellerOfferStatus::Active) {
            $offer->status = SellerOfferStatus::OutOfStock;
            $offer->save();
        } elseif ($quantity > 0 && $offer->status === SellerOfferStatus::OutOfStock) {
            $offer->status = SellerOfferStatus::Active;
            $offer->save();
        }
    }

    /**
     * Resolve seller.
     *
     * @param  int  $sellerId
     * @param  ?Authenticatable  $actor
     * @return Seller
     *
     * @throws ValidationException
     */
    protected function resolveSeller(int $sellerId, ?Authenticatable $actor): Seller
    {
        if ($actor instanceof Seller && (int) $actor->id !== $sellerId) {
            throw ValidationException::withMessages([
                'seller_id' => 'You may only manage offers for your own seller.',
            ]);
        }

        return Seller::query()->findOrFail($sellerId);
    }

    /**
     * Assert seller can manage offers.
     *
     * @param  Seller  $seller
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertSellerCanManageOffers(Seller $seller): void
    {
        if (! $seller->canSell()) {
            throw ValidationException::withMessages([
                'seller_id' => 'Seller must be approved and active to create offers.',
            ]);
        }
    }

    /**
     * Assert product sellable.
     *
     * @param  Product  $product
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertProductSellable(Product $product): void
    {
        if ($product->status !== ProductStatus::Active) {
            throw ValidationException::withMessages([
                'product_id' => 'Product must be active.',
            ]);
        }
    }

    /**
     * Assert variant belongs to product.
     *
     * @param  Product  $product
     * @param  int  $variantId
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertVariantBelongsToProduct(Product $product, int $variantId): void
    {
        $exists = ProductVariant::query()
            ->where('id', $variantId)
            ->where('product_id', $product->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Variant does not belong to the product.',
            ]);
        }
    }

    /**
     * Assert actor owns offer.
     *
     * @param  SellerOffer  $offer
     * @param  ?Authenticatable  $actor
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertActorOwnsOffer(SellerOffer $offer, ?Authenticatable $actor): void
    {
        if ($actor instanceof Seller && (int) $actor->id !== (int) $offer->seller_id) {
            throw ValidationException::withMessages([
                'offer' => 'You may only manage your own offers.',
            ]);
        }
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
