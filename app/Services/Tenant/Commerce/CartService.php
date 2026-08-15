<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Catalog\ProductAvailability;
use App\Enums\Tenant\Commerce\CartStatus;
use App\Models\Tenant\Cart;
use App\Models\Tenant\CartItem;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Product\ProductAvailabilityService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Customer shopping cart operations.
 */
class CartService
{
    public function __construct(
        private readonly CommerceSettingService $commerceSettings,
        private readonly ProductAvailabilityService $availability,
    ) {}

    /**
     * Get or create the customer's active cart.
     */
    public function getOrCreateActiveCart(Customer $customer): Cart
    {
        $cart = Cart::query()
            ->where('customer_id', $customer->id)
            ->where('status', CartStatus::Active)
            ->first();

        if ($cart !== null) {
            return $cart;
        }

        return Cart::query()->create([
            'customer_id' => $customer->id,
            'currency' => $this->commerceSettings->currencyCode(),
            'status' => CartStatus::Active,
        ]);
    }

    /**
     * Load the active cart with line items and pricing relations.
     */
    public function getCart(Customer $customer): Cart
    {
        $cart = $this->getOrCreateActiveCart($customer);

        return $cart->load([
            'items.product.prices',
            'items.productVariant.prices',
        ]);
    }

    /**
     * Add a product (and optional variant) line using server-resolved unit price.
     *
     * @throws ValidationException
     */
    public function addItem(
        Customer $customer,
        int $productId,
        ?int $variantId,
        int $quantity,
    ): CartItem {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        return DB::transaction(function () use ($customer, $productId, $variantId, $quantity): CartItem {
            $cart = $this->getOrCreateActiveCart($customer);
            $product = Product::query()->findOrFail($productId);
            $variant = $this->resolveVariant($product, $variantId);

            $this->assertPurchasable($product, $variant, $quantity);

            $unitPrice = $this->resolveUnitPrice($product, $variant, $cart->currency);
            $existing = $this->findLine($cart, $product->id, $variant?->id);

            if ($existing !== null) {
                $newQuantity = $existing->quantity + $quantity;
                $this->assertPurchaseQuantityLimits($product, $variant, $newQuantity);
                $this->assertStockAllowsQuantity($product, $variant, $newQuantity);

                $existing->quantity = $newQuantity;
                $existing->unit_price = $unitPrice;
                $existing->subtotal = Money::mul($unitPrice, (string) $newQuantity);
                $existing->save();

                return $existing->fresh(['product.prices', 'productVariant.prices']) ?? $existing;
            }

            $item = $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => Money::mul($unitPrice, (string) $quantity),
            ]);

            return $item->fresh(['product.prices', 'productVariant.prices']) ?? $item;
        });
    }

    /**
     * Update a cart line quantity.
     *
     * @throws ValidationException
     */
    public function updateItem(Customer $customer, CartItem $item, int $quantity): CartItem
    {
        $this->assertItemOwnership($customer, $item);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        return DB::transaction(function () use ($item, $quantity): CartItem {
            $item->loadMissing(['product', 'productVariant', 'cart']);
            $product = $item->product;
            $variant = $item->productVariant;

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product_id' => 'Cart item product is missing.',
                ]);
            }

            $this->assertPurchasable($product, $variant, $quantity);

            $unitPrice = $this->resolveUnitPrice($product, $variant, $item->cart->currency);
            $item->quantity = $quantity;
            $item->unit_price = $unitPrice;
            $item->subtotal = Money::mul($unitPrice, (string) $quantity);
            $item->save();

            return $item->fresh(['product.prices', 'productVariant.prices']) ?? $item;
        });
    }

    /**
     * Remove a cart line.
     */
    public function removeItem(Customer $customer, CartItem $item): void
    {
        $this->assertItemOwnership($customer, $item);
        $item->delete();
    }

    /**
     * Clear all lines from the active cart.
     */
    public function clear(Customer $customer): void
    {
        $cart = $this->getOrCreateActiveCart($customer);
        $cart->items()->delete();
    }

    /**
     * Recalculate line subtotals from stored unit prices.
     */
    public function recalculateItemSubtotals(Cart $cart): void
    {
        $cart->loadMissing('items');

        foreach ($cart->items as $item) {
            $item->subtotal = Money::mul((string) $item->unit_price, (string) $item->quantity);
            $item->save();
        }
    }

    /**
     * Cart money totals (discounts/tax/shipping filled at checkout).
     *
     * @return array{
     *     subtotal: string,
     *     discount_total: string,
     *     tax_total: string,
     *     shipping_total: string,
     *     grand_total: string
     * }
     */
    public function totals(Cart $cart): array
    {
        $cart->loadMissing('items');

        $subtotal = '0.00';

        foreach ($cart->items as $item) {
            $subtotal = Money::add($subtotal, (string) $item->subtotal);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'grand_total' => $subtotal,
        ];
    }

    /**
     * Ensure the cart item belongs to the customer's active cart.
     */
    public function assertItemOwnership(Customer $customer, CartItem $item): void
    {
        $item->loadMissing('cart');

        if ($item->cart === null || (int) $item->cart->customer_id !== (int) $customer->id) {
            throw new AccessDeniedHttpException('Cart item does not belong to this customer.');
        }

        if ($item->cart->status !== CartStatus::Active) {
            throw new NotFoundHttpException('Cart item is not on an active cart.');
        }
    }

    /**
     * Refresh line prices and revalidate purchasability (used at checkout).
     *
     * @throws ValidationException
     */
    public function revalidateItem(CartItem $item, string $currency): void
    {
        $item->loadMissing(['product', 'productVariant']);
        $product = $item->product;
        $variant = $item->productVariant;

        if ($product === null) {
            throw ValidationException::withMessages([
                'items' => 'A cart line references a missing product.',
            ]);
        }

        $this->assertPurchasable($product, $variant, $item->quantity);

        $unitPrice = $this->resolveUnitPrice($product, $variant, $currency);
        $item->unit_price = $unitPrice;
        $item->subtotal = Money::mul($unitPrice, (string) $item->quantity);
        $item->save();
    }

    /**
     * @throws ValidationException
     */
    protected function assertPurchasable(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (! $this->availability->isProductSellable($product)) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is not available for purchase.',
            ]);
        }

        if ($variant !== null && ! $variant->is_active) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'This product variant is not available for purchase.',
            ]);
        }

        if ($product->has_variants && $variant === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'A product variant is required for this product.',
            ]);
        }

        $availability = $variant !== null
            ? $this->availability->forVariant($variant, $product)
            : $this->availability->forProduct($product);

        if (in_array($availability, [ProductAvailability::Unavailable, ProductAvailability::OutOfStock], true)) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock for this product.',
            ]);
        }

        $this->assertPurchaseQuantityLimits($product, $variant, $quantity);
        $this->assertStockAllowsQuantity($product, $variant, $quantity);
    }

    /**
     * @throws ValidationException
     */
    protected function assertPurchaseQuantityLimits(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $min = $variant?->minimum_purchase_quantity ?? $product->minimum_purchase_quantity;
        $max = $variant?->maximum_purchase_quantity ?? $product->maximum_purchase_quantity;

        if ($min !== null && $quantity < $min) {
            throw ValidationException::withMessages([
                'quantity' => "Minimum purchase quantity is {$min}.",
            ]);
        }

        if ($max !== null && $quantity > $max) {
            throw ValidationException::withMessages([
                'quantity' => "Maximum purchase quantity is {$max}.",
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertStockAllowsQuantity(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $allowBackorder = $variant !== null
            ? (bool) ($variant->allow_backorder ?? $product->allow_backorder)
            : (bool) $product->allow_backorder;

        if ($allowBackorder) {
            return;
        }

        $available = $variant !== null
            ? $this->sumAvailable($variant)
            : $this->sumAvailable($product);

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock for the requested quantity.',
            ]);
        }
    }

    protected function sumAvailable(Product|ProductVariant $stockable): int
    {
        $stockable->loadMissing('inventories');

        return (int) $stockable->inventories->sum(
            fn ($inventory): int => $inventory->availableQuantity()
        );
    }

    /**
     * @throws ValidationException
     */
    protected function resolveVariant(Product $product, ?int $variantId): ?ProductVariant
    {
        if ($variantId === null) {
            return null;
        }

        $variant = ProductVariant::query()
            ->whereKey($variantId)
            ->where('product_id', $product->id)
            ->first();

        if ($variant === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The selected variant does not belong to this product.',
            ]);
        }

        return $variant;
    }

    /**
     * Resolve unit price from ProductPrice (variant preferred, else product). Never client-supplied.
     *
     * @throws ValidationException
     */
    protected function resolveUnitPrice(Product $product, ?ProductVariant $variant, string $currency): string
    {
        $price = null;

        if ($variant !== null) {
            $price = $this->activePriceFor($variant, $currency);
        }

        $price ??= $this->activePriceFor($product, $currency);

        if ($price === null) {
            throw ValidationException::withMessages([
                'product_id' => 'No active price is configured for this product.',
            ]);
        }

        return (string) $price->amount;
    }

    protected function activePriceFor(Product|ProductVariant $priceable, string $currency): ?ProductPrice
    {
        $now = now();

        return $priceable->prices()
            ->where('is_active', true)
            ->where('currency', $currency)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('id')
            ->first();
    }

    protected function findLine(Cart $cart, int $productId, ?int $variantId): ?CartItem
    {
        return $cart->items()
            ->where('product_id', $productId)
            ->when(
                $variantId === null,
                fn ($query) => $query->whereNull('product_variant_id'),
                fn ($query) => $query->where('product_variant_id', $variantId),
            )
            ->first();
    }
}
