<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\CartStatus;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Jobs\MarkAbandonedCartsJob;
use App\Listeners\Commerce\CommerceAnalyticsListener;
use App\Models\Tenant\Cart;
use App\Models\Tenant\CommerceEvent;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Unit;
use App\Models\Tenant\Warehouse;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CommerceAnalyticsService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Commerce\WishlistService;
use App\Services\Tenant\Inventory\InventoryService;
use Database\Seeders\Landlord\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (commerceAnalyticsMigrationFiles() as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    config([
        'notifications.queue' => false,
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
    ]);

    $this->seed(NotificationTemplateSeeder::class);
});

/**
 * @return list<string>
 */
function commerceAnalyticsMigrationFiles(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034315_create_product_prices_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034321_create_inventory_movements_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_060003_create_carts_table.php',
        '2026_08_15_060004_create_cart_items_table.php',
        '2026_08_15_080010_add_abandoned_fields_to_carts_table.php',
        '2026_08_15_090200_create_wishlists_table.php',
        '2026_08_15_090201_create_wishlist_items_table.php',
        '2026_08_15_090202_create_product_stock_subscriptions_table.php',
        '2026_08_15_090210_create_commerce_events_table.php',
    ];
}

test('commerce analytics service records events with subject and payload', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create();

    $event = app(CommerceAnalyticsService::class)->record('test.event', $product, $customer, [
        'foo' => 'bar',
    ]);

    expect($event->event_name)->toBe('test.event')
        ->and($event->customer_id)->toBe($customer->id)
        ->and($event->subject_type)->toBe($product->getMorphClass())
        ->and($event->subject_id)->toBe($product->id)
        ->and($event->payload)->toBe(['foo' => 'bar'])
        ->and($event->occurred_at)->not->toBeNull();
});

test('order created and paid listeners record analytics events', function (): void {
    $customer = Customer::factory()->create();
    $order = new Order([
        'order_number' => 'ORD-100',
        'customer_id' => $customer->id,
        'currency' => 'NGN',
        'grand_total' => '100.00',
    ]);
    $order->id = 1;
    $order->setRelation('customer', $customer);

    $listener = app(CommerceAnalyticsListener::class);
    $listener->handleOrderCreated(new OrderCreated($order));
    $listener->handleOrderPaid(new OrderPaid($order));

    expect(CommerceEvent::query()->pluck('event_name')->all())->toBe(['order.created', 'order.paid']);
});

test('abandoned cart job records cart abandoned analytics event', function (): void {
    Event::fake();

    app(CommerceSettingService::class)->set('cart.abandon_after_hours', '1');

    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create([
        'brand_id' => null,
        'unit_id' => Unit::factory()->create()->id,
        'name' => 'Analytics Cart Product',
        'slug' => 'analytics-cart-product',
        'allow_backorder' => false,
    ]);
    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => '50.00',
        'is_active' => true,
    ]);

    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $product);
    app(InventoryService::class)->adjust($inventory, 10, InventoryMovementType::OpeningStock, 'Opening');

    app(CartService::class)->addItem($customer, $product->id, null, 1);

    Cart::query()->update([
        'updated_at' => now()->subHours(2),
    ]);

    app(MarkAbandonedCartsJob::class)->handle(
        app(CommerceSettingService::class),
        app(NotificationService::class),
        app(CommerceAnalyticsService::class),
    );

    expect(CommerceEvent::query()->where('event_name', 'cart.abandoned')->count())->toBe(1)
        ->and(Cart::query()->first()?->status)->toBe(CartStatus::Abandoned);
});

test('wishlist add records analytics through service', function (): void {
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create([
        'brand_id' => null,
        'unit_id' => Unit::factory()->create()->id,
        'name' => 'Analytics Wishlist Product',
        'slug' => 'analytics-wishlist-product',
        'allow_backorder' => false,
    ]);

    app(WishlistService::class)->addItem($customer, $product->id);

    expect(CommerceEvent::query()->where('event_name', 'wishlist.item_added')->count())->toBe(1);
});
