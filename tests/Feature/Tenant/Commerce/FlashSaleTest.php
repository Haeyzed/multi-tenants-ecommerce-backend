<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\CouponType;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Events\FlashSaleEnded;
use App\Events\FlashSaleItemSoldOut;
use App\Events\FlashSaleStarted;
use App\Events\OrderCreated;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\FlashSale;
use App\Models\Tenant\FlashSaleItem;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\FlashSaleService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);

    foreach (flashSaleMigrationFiles() as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

/**
 * @return list<string>
 */
function flashSaleMigrationFiles(): array
{
    return [
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
        '2026_08_15_090101_create_coupons_table.php',
        '2026_08_15_090102_create_coupon_usages_table.php',
        '2026_08_15_090103_create_coupon_product_table.php',
        '2026_08_15_090104_create_coupon_category_table.php',
        '2026_08_15_090105_create_promotions_table.php',
        '2026_08_15_090106_create_promotion_product_table.php',
        '2026_08_15_090107_create_promotion_category_table.php',
        '2026_08_15_090108_add_coupon_and_promotion_fields_to_orders_table.php',
        '2026_08_15_100402_create_customer_segments_table.php',
        '2026_08_16_110000_create_customer_groups_table.php',
        '2026_08_16_140000_create_customer_segment_members_table.php',
        '2026_08_16_130000_create_flash_sales_table.php',
        '2026_08_16_130001_create_flash_sale_items_table.php',
        '2026_08_16_130002_add_customer_segment_id_to_flash_sale_items_table.php',
    ];
}

/**
 * @return array{customer: Customer, address: CustomerAddress, product: Product, inventory: Inventory}
 */
function flashSaleFixture(int $stock = 10, string $price = '100.00'): array
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

test('flash sale price replaces base price at checkout', function (): void {
    Event::fake([OrderCreated::class, FlashSaleItemSoldOut::class]);

    $fixture = flashSaleFixture(stock: 10, price: '100.00');

    $sale = FlashSale::factory()->create([
        'stack_with_coupons' => true,
    ]);

    FlashSaleItem::factory()->create([
        'flash_sale_id' => $sale->id,
        'product_id' => $fixture['product']->id,
        'sale_price' => '70.00',
        'qty_limit' => 10,
    ]);

    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 2);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    expect($order->subtotal)->toBe('140.00')
        ->and($order->grand_total)->toBe('140.00')
        ->and($order->items->first()->unit_price)->toBe('70.00')
        ->and($order->items->first()->metadata['flash_sale_item_id'] ?? null)->not->toBeNull()
        ->and(FlashSaleItem::query()->first()->sold_qty)->toBe(2);
});

test('oversell is prevented with lockForUpdate sequential consumption', function (): void {
    Event::fake([OrderCreated::class, FlashSaleItemSoldOut::class]);

    $fixture = flashSaleFixture(stock: 10, price: '100.00');
    $sale = FlashSale::factory()->create();
    $item = FlashSaleItem::factory()->create([
        'flash_sale_id' => $sale->id,
        'product_id' => $fixture['product']->id,
        'sale_price' => '50.00',
        'qty_limit' => 3,
        'sold_qty' => 0,
    ]);

    $service = app(FlashSaleService::class);

    $cartService = app(CartService::class);
    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 2);
    $cart = $cartService->getCart($fixture['customer']);

    DB::transaction(function () use ($service, $fixture, $cart): void {
        $service->consumeForCheckout($fixture['customer'], $cart);
    });

    expect($item->fresh()->sold_qty)->toBe(2);

    $secondCustomer = Customer::factory()->create();
    $cartService->addItem($secondCustomer, $fixture['product']->id, null, 2);
    $secondCart = $cartService->getCart($secondCustomer);

    expect(fn () => DB::transaction(function () use ($service, $secondCustomer, $secondCart): void {
        $service->consumeForCheckout($secondCustomer, $secondCart);
    }))->toThrow(ValidationException::class);

    expect($item->fresh()->sold_qty)->toBe(2);
});

test('qty_limit is enforced at checkout', function (): void {
    Event::fake([OrderCreated::class, FlashSaleItemSoldOut::class]);

    $fixture = flashSaleFixture(stock: 10, price: '100.00');
    $sale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $sale->id,
        'product_id' => $fixture['product']->id,
        'sale_price' => '60.00',
        'qty_limit' => 1,
    ]);

    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 2);

    expect(fn () => $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]))->toThrow(ValidationException::class);
});

test('per_customer_limit is enforced at checkout', function (): void {
    Event::fake([OrderCreated::class, FlashSaleItemSoldOut::class]);

    $fixture = flashSaleFixture(stock: 10, price: '100.00');
    $sale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $sale->id,
        'product_id' => $fixture['product']->id,
        'sale_price' => '55.00',
        'qty_limit' => 10,
        'per_customer_limit' => 1,
    ]);

    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 2);

    expect(fn () => $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]))->toThrow(ValidationException::class);

    $cartService->clear($fixture['customer']);
    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    expect($order->items->first()->unit_price)->toBe('55.00')
        ->and(FlashSaleItem::query()->first()->sold_qty)->toBe(1);

    // After the customer hits their limit, resolveSalePrice falls back to the base price.
    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);
    $cart = $cartService->getCart($fixture['customer']);

    expect($cart->items->first()->unit_price)->toBe('100.00');
});

test('stack_with_coupons false excludes flash lines from coupon discount', function (): void {
    Event::fake([OrderCreated::class, FlashSaleItemSoldOut::class]);

    $fixture = flashSaleFixture(stock: 10, price: '100.00');

    $regularProduct = Product::factory()->active()->create(['allow_backorder' => false]);
    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $regularProduct->id,
        'currency' => 'NGN',
        'amount' => '100.00',
        'is_active' => true,
    ]);
    $warehouse = Warehouse::query()->where('is_default', true)->first()
        ?? Warehouse::factory()->create(['is_default' => true]);
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $regularProduct);
    app(InventoryService::class)->adjust($inventory, 10, InventoryMovementType::OpeningStock, 'Opening');

    $sale = FlashSale::factory()->create([
        'stack_with_coupons' => false,
    ]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $sale->id,
        'product_id' => $fixture['product']->id,
        'sale_price' => '70.00',
    ]);

    Coupon::factory()->create([
        'code' => 'SAVE10',
        'type' => CouponType::Percentage,
        'value' => '10.00',
    ]);

    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);
    $cartService->addItem($fixture['customer'], $regularProduct->id, null, 1);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'coupon_code' => 'SAVE10',
    ]);

    // Flash line 70 + regular 100 = 170; coupon 10% only on regular = 10.
    expect($order->subtotal)->toBe('170.00')
        ->and($order->discount_total)->toBe('10.00')
        ->and($order->grand_total)->toBe('160.00');

    $flashLine = $order->items->firstWhere('product_id', $fixture['product']->id);
    $regularLine = $order->items->firstWhere('product_id', $regularProduct->id);

    expect($flashLine->discount_amount)->toBe('0.00')
        ->and($regularLine->discount_amount)->toBe('10.00');
});

test('sold out event fires when qty_limit is reached', function (): void {
    Event::fake([OrderCreated::class, FlashSaleItemSoldOut::class]);

    $fixture = flashSaleFixture(stock: 10, price: '100.00');
    $sale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $sale->id,
        'product_id' => $fixture['product']->id,
        'sale_price' => '40.00',
        'qty_limit' => 2,
    ]);

    app(CartService::class)->addItem($fixture['customer'], $fixture['product']->id, null, 2);
    app(CheckoutService::class)->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    Event::assertDispatched(FlashSaleItemSoldOut::class);
});

test('flash sale item restricted by customer segment is not applied to outsiders', function (): void {
    $fixture = flashSaleFixture(stock: 10, price: '100.00');
    $returning = Customer::factory()->create();
    Order::factory()->create([
        'customer_id' => $returning->id,
        'status' => OrderStatus::Confirmed,
        'grand_total' => '25.00',
        'placed_at' => now(),
    ]);

    $segment = CustomerSegment::factory()->rule(CustomerSegmentRule::ReturningCustomer)->create();
    $sale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $sale->id,
        'product_id' => $fixture['product']->id,
        'sale_price' => '40.00',
        'customer_segment_id' => $segment->id,
    ]);

    $service = app(FlashSaleService::class);
    $forReturning = $service->resolveSalePrice($fixture['product'], null, $returning);

    expect($service->resolveSalePrice($fixture['product'], null, $fixture['customer']))->toBeNull()
        ->and($forReturning)->not->toBeNull()
        ->and($forReturning['price'])->toBe('40.00');
});

test('flash sale lifecycle events implement ShouldBroadcastNow', function (): void {
    $sale = FlashSale::factory()->make(['id' => 1]);
    $item = FlashSaleItem::factory()->make(['id' => 1, 'flash_sale_id' => 1]);

    expect(new FlashSaleStarted($sale))->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and(new FlashSaleEnded($sale))->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and(new FlashSaleItemSoldOut($item))->toBeInstanceOf(ShouldBroadcastNow::class);
});
