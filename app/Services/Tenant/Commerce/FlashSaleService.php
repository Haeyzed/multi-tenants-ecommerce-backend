<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\FlashSaleStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Events\FlashSaleEnded;
use App\Events\FlashSaleItemSoldOut;
use App\Events\FlashSaleStarted;
use App\Models\Tenant\Cart;
use App\Models\Tenant\CartItem;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\FlashSale;
use App\Models\Tenant\FlashSaleItem;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Customer\CustomerSegmentationService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRUD, pricing resolution, and checkout consumption for flash sales.
 */
class FlashSaleService
{
    /**
     * Create a new class instance.
     *
     * @param  CustomerSegmentationService  $segmentation
     */
    public function __construct(
        private readonly CustomerSegmentationService $segmentation,
    ) {}

    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, is_active?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, FlashSale>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = FlashSale::query()->with('items')->latest('id');

        if (array_key_exists('is_active', $params) && $params['is_active'] !== null) {
            $query->where('is_active', (bool) $params['is_active']);
        }

        if (! empty($params['search'])) {
            $search = (string) $params['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * Active flash sales in the current schedule window.
     *
     * @return Collection<int, FlashSale>
     */
    public function listActive(): Collection
    {
        if (! Schema::hasTable('flash_sales')) {
            return collect();
        }

        $now = now();

        return FlashSale::query()
            ->with('items')
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Create a resource.
     *
     * @param  array<string, mixed>  $data
     * @return FlashSale
     */
    public function store(array $data): FlashSale
    {
        $flashSale = FlashSale::query()->create($this->attributesFromData($data));

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $itemData) {
                if (! is_array($itemData)) {
                    continue;
                }

                $this->addItem($flashSale, $itemData);
            }
        }

        $flashSale = $flashSale->fresh(['items']) ?? $flashSale;
        $this->dispatchLifecycleEvents($flashSale);

        return $flashSale;
    }

    /**
     * Retrieve a single resource.
     *
     * @param  FlashSale  $flashSale
     * @return FlashSale
     */
    public function show(FlashSale $flashSale): FlashSale
    {
        return $flashSale->load(['items.product', 'items.productVariant', 'items.customerGroup']);
    }

    /**
     * Update a resource.
     *
     * @param  FlashSale  $flashSale
     * @param  array<string, mixed>  $data
     * @return FlashSale
     */
    public function update(FlashSale $flashSale, array $data): FlashSale
    {
        $previousStatus = $flashSale->status();

        $flashSale->fill($this->attributesFromData($data, $flashSale));
        $flashSale->save();

        if (array_key_exists('items', $data) && is_array($data['items'])) {
            $this->syncItems($flashSale, $data['items']);
        }

        $flashSale = $flashSale->fresh(['items']) ?? $flashSale;
        $this->dispatchLifecycleEvents($flashSale, $previousStatus);

        return $flashSale;
    }

    /**
     * Delete a resource.
     *
     * @param  FlashSale  $flashSale
     * @return void
     */
    public function destroy(FlashSale $flashSale): void
    {
        $flashSale->delete();
    }

    /**
     * Add item.
     *
     * @param  FlashSale  $flashSale
     * @param  array<string, mixed>  $data
     * @return FlashSaleItem
     */
    public function addItem(FlashSale $flashSale, array $data): FlashSaleItem
    {
        return $flashSale->items()->create($this->itemAttributesFromData($data));
    }

    /**
     * Update item.
     *
     * @param  FlashSaleItem  $item
     * @param  array<string, mixed>  $data
     * @return FlashSaleItem
     */
    public function updateItem(FlashSaleItem $item, array $data): FlashSaleItem
    {
        $item->fill($this->itemAttributesFromData($data, $item));
        $item->save();

        return $item->fresh(['product', 'productVariant', 'customerGroup']) ?? $item;
    }

    /**
     * Remove item.
     *
     * @param  FlashSaleItem  $item
     * @return void
     */
    public function removeItem(FlashSaleItem $item): void
    {
        $item->delete();
    }

    /**
     * Replace flash sale items from a payload list.
     *
     * @param  FlashSale  $flashSale
     * @param  list<array<string, mixed>>  $items
     * @return void
     */
    public function syncItems(FlashSale $flashSale, array $items): void
    {
        $flashSale->items()->delete();

        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }

            $this->addItem($flashSale, $itemData);
        }
    }

    /**
     * Resolve the best active flash sale price for a product/variant.
     *
     * @param  Product  $product
     * @param  ?ProductVariant  $variant
     * @param  ?Customer  $customer
     * @return array{price: string, item: FlashSaleItem, sale: FlashSale}|null
     */
    public function resolveSalePrice(
        Product $product,
        ?ProductVariant $variant = null,
        ?Customer $customer = null,
    ): ?array {
        if (! Schema::hasTable('flash_sales') || ! Schema::hasTable('flash_sale_items')) {
            return null;
        }

        $now = now();
        $variantId = $variant?->id;

        $candidates = FlashSaleItem::query()
            ->with(['flashSale', 'customerSegment'])
            ->where('product_id', $product->id)
            ->where(function (Builder $query) use ($variantId): void {
                if ($variantId !== null) {
                    $query->where('product_variant_id', $variantId)
                        ->orWhereNull('product_variant_id');
                } else {
                    $query->whereNull('product_variant_id');
                }
            })
            ->whereHas('flashSale', function (Builder $query) use ($now): void {
                $query->where('is_active', true)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now);
            })
            ->orderByDesc('id')
            ->get();

        $best = null;

        foreach ($candidates as $item) {
            $sale = $item->flashSale;

            if ($sale === null || ! $sale->isCurrentlyActive()) {
                continue;
            }

            if ($item->isSoldOut()) {
                continue;
            }

            if ($item->customer_group_id !== null) {
                if ($customer === null || (int) $customer->customer_group_id !== (int) $item->customer_group_id) {
                    continue;
                }
            }

            if ($item->customer_segment_id !== null) {
                if ($customer === null || ! $this->customerMatchesSegment($customer, $item)) {
                    continue;
                }
            }

            if ($item->per_customer_limit !== null && $customer !== null) {
                $purchased = $this->customerPurchasedQuantity($customer, $item);

                if ($purchased >= $item->per_customer_limit) {
                    continue;
                }
            }

            // Prefer exact variant matches over product-level rows.
            $isExactVariant = $variantId !== null && (int) $item->product_variant_id === (int) $variantId;
            $bestIsExact = $best !== null
                && $variantId !== null
                && (int) $best['item']->product_variant_id === (int) $variantId;

            if ($best === null || ($isExactVariant && ! $bestIsExact) || (
                $isExactVariant === $bestIsExact
                && bccomp((string) $item->sale_price, $best['price'], 2) < 0
            )) {
                $best = [
                    'price' => Money::add((string) $item->sale_price, '0'),
                    'item' => $item,
                    'sale' => $sale,
                ];
            }
        }

        return $best;
    }

    /**
     * Cart item IDs that are priced by a non-stackable flash sale.
     *
     * @param  Cart  $cart
     * @param  ?Customer  $customer
     * @return list<int>
     */
    public function nonStackableFlashCartItemIds(Cart $cart, ?Customer $customer = null): array
    {
        if (! Schema::hasTable('flash_sales')) {
            return [];
        }

        $customer ??= $cart->customer;
        $excluded = [];

        foreach ($cart->items as $item) {
            $resolved = $this->resolveSalePrice($item->product, $item->productVariant, $customer);

            if ($resolved === null) {
                continue;
            }

            if (! $resolved['sale']->stack_with_coupons) {
                $excluded[] = (int) $item->id;
            }
        }

        return $excluded;
    }

    /**
     * Whether the given cart line currently uses a flash sale unit price.
     *
     * @param  CartItem  $item
     * @param  ?Customer  $customer
     * @return bool
     */
    public function cartItemUsesFlashSale(CartItem $item, ?Customer $customer = null): bool
    {
        $item->loadMissing(['product', 'productVariant', 'cart.customer']);
        $customer ??= $item->cart?->customer;

        $resolved = $this->resolveSalePrice($item->product, $item->productVariant, $customer);

        if ($resolved === null) {
            return false;
        }

        return bccomp((string) $item->unit_price, $resolved['price'], 2) === 0;
    }

    /**
     * Lock flash sale items and increment sold_qty for cart lines on checkout.
     *
     * @param  Customer  $customer
     * @param  Cart  $cart
     * @return array<int, array{flash_sale_item_id: int, flash_sale_id: int, sale_price: string}>
     *
     * @throws ValidationException
     */
    public function consumeForCheckout(Customer $customer, Cart $cart): array
    {
        if (! Schema::hasTable('flash_sales') || ! Schema::hasTable('flash_sale_items')) {
            return [];
        }

        $cart->loadMissing(['items.product', 'items.productVariant']);
        $metadataByCartItemId = [];

        foreach ($cart->items as $cartItem) {
            $resolved = $this->resolveSalePrice($cartItem->product, $cartItem->productVariant, $customer);

            if ($resolved === null) {
                continue;
            }

            // Only consume when the cart line is actually at the flash price.
            if (bccomp((string) $cartItem->unit_price, $resolved['price'], 2) !== 0) {
                continue;
            }

            $locked = FlashSaleItem::query()
                ->whereKey($resolved['item']->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'cart' => 'A flash sale item is no longer available.',
                ]);
            }

            $sale = FlashSale::query()
                ->whereKey($locked->flash_sale_id)
                ->lockForUpdate()
                ->first();

            if ($sale === null || ! $sale->isCurrentlyActive()) {
                throw ValidationException::withMessages([
                    'cart' => 'A flash sale has ended and is no longer available.',
                ]);
            }

            if ($locked->customer_group_id !== null
                && (int) $customer->customer_group_id !== (int) $locked->customer_group_id) {
                throw ValidationException::withMessages([
                    'cart' => 'A flash sale is not available for your customer group.',
                ]);
            }

            if ($locked->customer_segment_id !== null && ! $this->customerMatchesSegment($customer, $locked)) {
                throw ValidationException::withMessages([
                    'cart' => 'A flash sale is not available for your customer segment.',
                ]);
            }

            $quantity = (int) $cartItem->quantity;

            if ($locked->qty_limit !== null && ($locked->sold_qty + $quantity) > $locked->qty_limit) {
                throw ValidationException::withMessages([
                    'cart' => 'Flash sale quantity limit exceeded for one or more items.',
                ]);
            }

            if ($locked->per_customer_limit !== null) {
                $alreadyPurchased = $this->customerPurchasedQuantity($customer, $locked);

                if (($alreadyPurchased + $quantity) > $locked->per_customer_limit) {
                    throw ValidationException::withMessages([
                        'cart' => 'Flash sale per-customer limit exceeded for one or more items.',
                    ]);
                }
            }

            $wasSoldOut = $locked->isSoldOut();
            $locked->sold_qty += $quantity;
            $locked->save();

            if (! $wasSoldOut && $locked->isSoldOut()) {
                event(new FlashSaleItemSoldOut($locked));
            }

            $metadataByCartItemId[(int) $cartItem->id] = [
                'flash_sale_item_id' => $locked->id,
                'flash_sale_id' => $sale->id,
                'sale_price' => Money::add((string) $locked->sale_price, '0'),
            ];
        }

        return $metadataByCartItemId;
    }

    /**
     * Quantity already purchased by the customer for a flash sale item (non-cancelled orders).
     *
     * @param  Customer  $customer
     * @param  FlashSaleItem  $item
     * @return int
     */
    public function customerPurchasedQuantity(Customer $customer, FlashSaleItem $item): int
    {
        if (! Schema::hasTable('order_items') || ! Schema::hasTable('orders')) {
            return 0;
        }

        return (int) OrderItem::query()
            ->where('metadata->flash_sale_item_id', $item->id)
            ->whereHas('order', function (Builder $query) use ($customer): void {
                $query->where('customer_id', $customer->id)
                    ->where('status', '!=', OrderStatus::Cancelled->value);
            })
            ->sum('quantity');
    }

    /**
     * Attributes from data.
     *
     * @param  array<string, mixed>  $data
     * @param  ?FlashSale  $existing
     * @return array<string, mixed>
     */
    protected function attributesFromData(array $data, ?FlashSale $existing = null): array
    {
        $name = array_key_exists('name', $data) ? (string) $data['name'] : ($existing?->name ?? '');
        $slug = array_key_exists('slug', $data) && filled($data['slug'])
            ? Str::slug((string) $data['slug'])
            : ($existing?->slug ?? Str::slug($name));

        $attributes = [
            'name' => $name !== '' ? $name : ($existing?->name ?? $slug),
            'slug' => $slug,
        ];

        foreach (['description', 'starts_at', 'ends_at', 'is_active', 'stack_with_coupons'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        return $attributes;
    }

    /**
     * Item attributes from data.
     *
     * @param  array<string, mixed>  $data
     * @param  ?FlashSaleItem  $existing
     * @return array<string, mixed>
     */
    protected function itemAttributesFromData(array $data, ?FlashSaleItem $existing = null): array
    {
        $attributes = [];

        foreach ([
            'product_id',
            'product_variant_id',
            'sale_price',
            'qty_limit',
            'per_customer_limit',
            'customer_group_id',
            'customer_segment_id',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            } elseif ($existing !== null) {
                $attributes[$key] = $existing->{$key};
            }
        }

        if (! array_key_exists('sold_qty', $attributes) && $existing === null) {
            $attributes['sold_qty'] = 0;
        }

        return $attributes;
    }

    /**
     * Dispatch lifecycle events.
     *
     * @param  FlashSale  $flashSale
     * @param  ?FlashSaleStatus  $previous
     * @return void
     */
    protected function dispatchLifecycleEvents(FlashSale $flashSale, ?FlashSaleStatus $previous = null): void
    {
        $current = $flashSale->status();

        if ($current === FlashSaleStatus::Active && $previous !== FlashSaleStatus::Active) {
            event(new FlashSaleStarted($flashSale));
        }

        if ($current === FlashSaleStatus::Ended && $previous === FlashSaleStatus::Active) {
            event(new FlashSaleEnded($flashSale));
        }

        if ($current === FlashSaleStatus::Inactive && $previous === FlashSaleStatus::Active) {
            event(new FlashSaleEnded($flashSale));
        }
    }

    /**
     * Customer matches segment.
     *
     * @param  Customer  $customer
     * @param  FlashSaleItem  $item
     * @return bool
     */
    protected function customerMatchesSegment(Customer $customer, FlashSaleItem $item): bool
    {
        $segment = $item->relationLoaded('customerSegment')
            ? $item->customerSegment
            : CustomerSegment::query()->find($item->customer_segment_id);

        if ($segment === null) {
            return false;
        }

        return $this->segmentation->matches($customer, $segment);
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 15;

        return max(1, min($perPage, 100));
    }
}
