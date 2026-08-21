<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\CartStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\OrderService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrationFiles = [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_031731_create_categories_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034307_create_category_product_table.php',
        '2026_08_15_034315_create_product_prices_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034321_create_inventory_movements_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_041028_create_customer_addresses_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060003_create_carts_table.php',
        '2026_08_15_060004_create_cart_items_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_060007_create_checkout_sessions_table.php',
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_16_160758_make_sellers_authenticatable_table.php',
        '2026_08_15_070003_create_seller_offers_table.php',
        '2026_08_15_070004_add_seller_offer_to_cart_and_order_items.php',
        '2026_08_15_080001_create_taxes_table.php',
        '2026_08_15_080002_create_tax_rates_table.php',
        '2026_08_15_080003_create_tax_zones_table.php',
        '2026_08_15_080004_create_tax_zone_locations_table.php',
        '2026_08_15_080005_create_tax_rules_table.php',
        '2026_08_15_080006_add_tax_snapshot_to_orders_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

/**
 * @return array{customer: Customer, address: CustomerAddress, product: Product, inventory: Inventory}
 */
function commerceFixture(int $stock = 10, string $price = '100.00'): array
{
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->default()->create();
    $product = Product::factory()->active()->create([
        'allow_backorder' => false,
    ]);

    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => $price,
        'is_active' => true,
    ]);

    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $product);
    app(InventoryService::class)->adjust($inventory, $stock, InventoryMovementType::OpeningStock, 'Opening');

    return [
        'customer' => $customer,
        'address' => $address,
        'product' => $product,
        'inventory' => $inventory->fresh(),
    ];
}

/**
 * @return array{product: Product, variant: ProductVariant}
 */
function simpleSkuProductFixture(string $sku = 'SAMSUNG-TV-001', string $price = '100.00'): array
{
    $product = Product::factory()->active()->create([
        'allow_backorder' => false,
        'has_variants' => false,
    ]);

    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'sku' => $sku,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    ProductPrice::query()->create([
        'priceable_type' => $product->getMorphClass(),
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => $price,
        'is_active' => true,
    ]);

    return [
        'product' => $product->fresh(['variants']),
        'variant' => $variant,
    ];
}

test('add item uses server price and ignores client unit price', function (): void {
    $fixture = commerceFixture();
    $cartService = app(CartService::class);

    $item = $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 2);

    expect($item->unit_price)->toBe('100.00')
        ->and($item->subtotal)->toBe('200.00')
        ->and($item->quantity)->toBe(2);
});

test('update remove and clear cart items', function (): void {
    $fixture = commerceFixture();
    $cartService = app(CartService::class);

    $item = $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);
    $updated = $cartService->updateItem($fixture['customer'], $item, 3);

    expect($updated->quantity)->toBe(3)
        ->and($updated->subtotal)->toBe('300.00');

    $cartService->removeItem($fixture['customer'], $updated);
    $cart = $cartService->getCart($fixture['customer']);
    expect($cart->items)->toHaveCount(0);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);
    $cartService->clear($fixture['customer']);
    expect($cartService->getCart($fixture['customer'])->items)->toHaveCount(0);
});

test('checkout creates order and reserves stock', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = commerceFixture(stock: 10);
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 2);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'notes' => 'Please deliver after noon',
    ]);

    expect($order->order_number)->toStartWith('ORD-')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->subtotal)->toBe('200.00')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->unit_price)->toBe('100.00')
        ->and($order->items->first()->inventory_id)->toBe($fixture['inventory']->id)
        ->and($fixture['inventory']->fresh()->reserved_quantity)->toBe(2)
        ->and($cartService->getOrCreateActiveCart($fixture['customer'])->status)->toBe(CartStatus::Active);

    $converted = $fixture['customer']->carts()->where('status', CartStatus::Converted)->first();
    expect($converted)->not->toBeNull();

    Event::assertDispatched(OrderCreated::class);
});

test('idempotent checkout with same key returns the same order', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = commerceFixture();
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    $first = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'idempotency_key' => 'checkout-key-1',
    ]);

    // New active cart after conversion — second call must short-circuit on key.
    $second = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'idempotency_key' => 'checkout-key-1',
    ]);

    expect($second->id)->toBe($first->id)
        ->and(Order::query()->count())->toBe(1);
});

test('cancel releases inventory reservation', function (): void {
    Event::fake([OrderCreated::class, OrderCancelled::class]);

    $fixture = commerceFixture(stock: 5);
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);
    $orderService = app(OrderService::class);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 3);
    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    expect($fixture['inventory']->fresh()->reserved_quantity)->toBe(3);

    $cancelled = $orderService->cancel($order);

    expect($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($fixture['inventory']->fresh()->reserved_quantity)->toBe(0);

    Event::assertDispatched(OrderCancelled::class);
});

test('customer cannot access another customer order', function (): void {
    $owner = Customer::factory()->create();
    $other = Customer::factory()->create();
    $order = Order::factory()->for($owner)->create();

    expect(fn () => app(OrderService::class)->customerShow($other, $order))
        ->toThrow(AccessDeniedHttpException::class);
});

test('checkout reserves stock from implicit sku variant when cart has no variant', function (): void {
    Event::fake([OrderCreated::class]);

    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->default()->create();
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    ['product' => $product] = simpleSkuProductFixture();

    $inventory = app(InventoryService::class)->assign($warehouse, $product, null, ['quantity' => 10]);

    app(CartService::class)->addItem($customer, $product->id, null, 2);

    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
    ]);

    expect($order->items->first()->inventory_id)->toBe($inventory->id)
        ->and($inventory->fresh()->reserved_quantity)->toBe(2);

    Event::assertDispatched(OrderCreated::class);
});

test('cart rejects quantity above implicit sku variant stock', function (): void {
    $customer = Customer::factory()->create();
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    ['product' => $product] = simpleSkuProductFixture();

    app(InventoryService::class)->assign($warehouse, $product, null, ['quantity' => 2]);

    expect(fn () => app(CartService::class)->addItem($customer, $product->id, null, 3))
        ->toThrow(ValidationException::class);
});
