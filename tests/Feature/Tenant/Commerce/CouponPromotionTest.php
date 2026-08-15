<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\CouponType;
use App\Enums\Tenant\Commerce\PromotionType;
use App\Events\OrderCreated;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\CouponUsage;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Promotion;
use App\Models\Tenant\ShippingMethod;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\CouponService;
use App\Services\Tenant\Commerce\DiscountService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);

    foreach (couponPromotionMigrationFiles() as $file) {
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
function couponPromotionMigrationFiles(): array
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
    ];
}

/**
 * @return array{customer: Customer, address: CustomerAddress, product: Product, inventory: Inventory}
 */
function couponPromotionFixture(int $stock = 10, string $price = '100.00'): array
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

test('percentage coupon applies discount at checkout and records usage', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = couponPromotionFixture(stock: 10, price: '100.00');
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    Coupon::factory()->create([
        'code' => 'SAVE10',
        'type' => CouponType::Percentage,
        'value' => '10.00',
    ]);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 2);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'coupon_code' => 'save10',
    ]);

    expect($order->subtotal)->toBe('200.00')
        ->and($order->discount_total)->toBe('20.00')
        ->and($order->coupon_code)->toBe('SAVE10')
        ->and($order->coupon_id)->not->toBeNull()
        ->and($order->grand_total)->toBe('180.00')
        ->and($order->items->first()->discount_amount)->toBe('20.00')
        ->and(CouponUsage::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('fixed coupon is capped at eligible subtotal', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = couponPromotionFixture(stock: 5, price: '50.00');
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    Coupon::factory()->fixed('100.00')->create([
        'code' => 'BIGFIX',
    ]);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'coupon_code' => 'BIGFIX',
    ]);

    expect($order->discount_total)->toBe('50.00')
        ->and($order->grand_total)->toBe('0.00');
});

test('invalid coupon is rejected at checkout', function (): void {
    $fixture = couponPromotionFixture();
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    expect(fn () => $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'coupon_code' => 'NOPE',
    ]))->toThrow(ValidationException::class);
});

test('coupon preview validates through discount service', function (): void {
    $fixture = couponPromotionFixture(price: '80.00');
    $cartService = app(CartService::class);

    Coupon::factory()->create([
        'code' => 'PREVIEW20',
        'type' => CouponType::Fixed,
        'value' => '20.00',
    ]);

    $cart = $cartService->getCart($fixture['customer']);
    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    $result = app(DiscountService::class)->previewCoupon(
        $fixture['customer'],
        $cartService->getCart($fixture['customer']),
        'preview20',
    );

    expect($result->amount)->toBe('20.00')
        ->and($result->coupon?->code)->toBe('PREVIEW20');
});

test('exclusive promotion wins over stackable promotions', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = couponPromotionFixture(stock: 10, price: '100.00');
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    Promotion::factory()->create([
        'slug' => 'stackable-five',
        'type' => PromotionType::FixedOffOrder,
        'value' => '5.00',
        'priority' => 1,
        'is_stackable' => true,
    ]);

    Promotion::factory()->exclusive(priority: 100)->create([
        'slug' => 'exclusive-twenty',
        'type' => PromotionType::PercentageOffOrder,
        'value' => '20.00',
    ]);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    expect($order->discount_total)->toBe('20.00')
        ->and($order->promotion_snapshot)->toHaveCount(1)
        ->and($order->promotion_snapshot[0]['slug'])->toBe('exclusive-twenty');
});

test('stackable promotions combine when no exclusive applies', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = couponPromotionFixture(stock: 10, price: '100.00');
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    Promotion::factory()->create([
        'slug' => 'stack-five',
        'type' => PromotionType::FixedOffOrder,
        'value' => '5.00',
        'priority' => 1,
        'is_stackable' => true,
    ]);

    Promotion::factory()->create([
        'slug' => 'stack-ten-percent',
        'type' => PromotionType::PercentageOffOrder,
        'value' => '10.00',
        'priority' => 2,
        'is_stackable' => true,
    ]);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    expect($order->discount_total)->toBe('15.00')
        ->and($order->promotion_snapshot)->toHaveCount(2);
});

test('free shipping promotion zeroes shipping total', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = couponPromotionFixture(stock: 10, price: '100.00');
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    Promotion::factory()->freeShipping()->create([
        'slug' => 'ship-free',
        'priority' => 5,
    ]);

    $shippingMethod = ShippingMethod::query()->create([
        'name' => 'Standard',
        'code' => 'STD',
        'amount' => '15.00',
        'is_active' => true,
    ]);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'shipping_method_id' => $shippingMethod->id,
    ]);

    expect($order->shipping_total)->toBe('0.00')
        ->and($order->grand_total)->toBe('100.00')
        ->and($order->promotion_snapshot[0]['free_shipping'])->toBeTrue();
});

test('coupon is skipped when exclusive promotion applies', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = couponPromotionFixture(stock: 10, price: '100.00');
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    Coupon::factory()->create([
        'code' => 'SKIPME',
        'type' => CouponType::Fixed,
        'value' => '30.00',
    ]);

    Promotion::factory()->exclusive(priority: 50)->create([
        'slug' => 'exclusive-only',
        'type' => PromotionType::FixedOffOrder,
        'value' => '10.00',
    ]);

    $cartService->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'coupon_code' => 'SKIPME',
    ]);

    expect($order->discount_total)->toBe('10.00')
        ->and($order->coupon_code)->toBeNull()
        ->and(CouponUsage::query()->count())->toBe(0);
});

test('admin coupon service stores and updates coupons', function (): void {
    $service = app(CouponService::class);

    $coupon = $service->store([
        'code' => 'admin-new',
        'name' => 'Admin Coupon',
        'type' => CouponType::Percentage->value,
        'value' => '15.00',
        'product_ids' => [],
        'category_ids' => [],
    ]);

    expect($coupon->code)->toBe('ADMIN-NEW');

    $updated = $service->update($coupon, [
        'name' => 'Updated Coupon',
        'type' => CouponType::Percentage->value,
        'value' => '15.00',
        'is_active' => false,
    ]);

    expect($updated->name)->toBe('Updated Coupon')
        ->and($updated->is_active)->toBeFalse();
});
