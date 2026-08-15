<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\ProductRelationType;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Models\Tenant\CommerceEvent;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductRelation;
use App\Models\Tenant\ProductView;
use App\Services\Tenant\Catalog\ProductRecommendationService;
use App\Services\Tenant\Catalog\ProductViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (productRecommendationMigrationFiles() as $file) {
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
function productRecommendationMigrationFiles(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_031731_create_categories_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034315_create_product_prices_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_050002_create_product_relations_table.php',
        '2026_08_15_050004_create_product_tags_tables.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_090210_create_commerce_events_table.php',
        '2026_08_15_100401_create_product_views_table.php',
    ];
}

test('related provider returns curated relations in sort order', function (): void {
    $product = Product::factory()->active()->create();
    $upsell = Product::factory()->active()->create();
    $related = Product::factory()->active()->create();

    ProductRelation::query()->create([
        'product_id' => $product->id,
        'related_product_id' => $upsell->id,
        'type' => ProductRelationType::Upsell,
        'sort_order' => 2,
    ]);

    ProductRelation::query()->create([
        'product_id' => $product->id,
        'related_product_id' => $related->id,
        'type' => ProductRelationType::Related,
        'sort_order' => 1,
    ]);

    $results = app(ProductRecommendationService::class)->recommend(['related'], $product);

    expect($results['related']->pluck('id')->all())->toBe([$related->id, $upsell->id]);
});

test('popular provider ranks products by sold quantity and skips the current product', function (): void {
    $current = Product::factory()->active()->create();
    $bestSeller = Product::factory()->active()->create();
    $slowSeller = Product::factory()->active()->create();

    $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);

    foreach ([[$current, 50], [$bestSeller, 20], [$slowSeller, 3]] as [$product, $quantity]) {
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => '10.00',
            'subtotal' => '10.00',
            'total' => '10.00',
        ]);
    }

    $results = app(ProductRecommendationService::class)->recommend(['popular'], $current);

    expect($results['popular']->pluck('id')->all())->toBe([$bestSeller->id, $slowSeller->id]);
});

test('cancelled orders do not count towards popularity', function (): void {
    $product = Product::factory()->active()->create();

    $order = Order::factory()->create([
        'status' => OrderStatus::Cancelled,
        'cancelled_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 25,
        'unit_price' => '10.00',
        'subtotal' => '10.00',
        'total' => '10.00',
    ]);

    $results = app(ProductRecommendationService::class)->recommend(['popular']);

    expect($results['popular'])->toBeEmpty();
});

test('viewing a product records a view row and a commerce event', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create();

    app(ProductViewService::class)->record($product, $customer, 'session-abc');

    expect(ProductView::query()->where('product_id', $product->id)->count())->toBe(1)
        ->and(CommerceEvent::query()->where('event_name', 'product.viewed')->count())->toBe(1);
});

test('recently viewed returns distinct products newest first for a customer', function (): void {
    $customer = Customer::factory()->create();
    $first = Product::factory()->active()->create();
    $second = Product::factory()->active()->create();

    $service = app(ProductViewService::class);

    $service->record($first, $customer);
    $service->record($second, $customer);
    $service->record($first, $customer);

    $recent = $service->recentlyViewed($customer);

    expect($recent->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('recently viewed is scoped to the viewer', function (): void {
    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();
    $product = Product::factory()->active()->create();

    $service = app(ProductViewService::class);
    $service->record($product, $customer);

    expect($service->recentlyViewed($otherCustomer))->toBeEmpty()
        ->and($service->recentlyViewed(null, 'unknown-session'))->toBeEmpty();
});

test('anonymous session views feed the recently viewed provider', function (): void {
    $product = Product::factory()->active()->create();
    $current = Product::factory()->active()->create();

    app(ProductViewService::class)->record($product, null, 'guest-session');

    $results = app(ProductRecommendationService::class)->recommend(
        types: ['recently_viewed'],
        product: $current,
        sessionKey: 'guest-session',
    );

    expect($results['recently_viewed']->pluck('id')->all())->toBe([$product->id]);
});

test('unknown recommendation types are ignored and no types runs every provider', function (): void {
    $product = Product::factory()->active()->create();
    $service = app(ProductRecommendationService::class);

    expect($service->recommend(['nonsense'], $product))->toBe([])
        ->and(array_keys($service->recommend([], $product)))
        ->toBe(['related', 'popular', 'recently_viewed']);
});
