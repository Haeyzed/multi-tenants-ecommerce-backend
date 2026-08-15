<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Models\Landlord\NotificationTemplate;
use App\Models\Tenant\CommerceEvent;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\ProductStockSubscription;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\Wishlist;
use App\Models\Tenant\WishlistItem;
use App\Notifications\TemplatedNotification;
use App\Services\Tenant\Commerce\WishlistService;
use App\Services\Tenant\Inventory\InventoryService;
use Database\Seeders\Landlord\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (wishlistMigrationFiles() as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed(NotificationTemplateSeeder::class);
    config([
        'notifications.queue' => false,
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
    ]);
});

/**
 * @return list<string>
 */
function wishlistMigrationFiles(): array
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
        '2026_08_15_090200_create_wishlists_table.php',
        '2026_08_15_090201_create_wishlist_items_table.php',
        '2026_08_15_090202_create_product_stock_subscriptions_table.php',
        '2026_08_15_090210_create_commerce_events_table.php',
    ];
}

/**
 * @return array{customer: Customer, product: Product}
 */
function wishlistFixture(int $stock = 10): array
{
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['allow_backorder' => false]);

    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => '100.00',
        'is_active' => true,
    ]);

    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $product);
    app(InventoryService::class)->adjust($inventory, $stock, InventoryMovementType::OpeningStock, 'Opening');

    return [
        'customer' => $customer,
        'product' => $product,
    ];
}

test('customer gets one wishlist and can add check and remove items', function (): void {
    $fixture = wishlistFixture();
    $service = app(WishlistService::class);

    $item = $service->addItem($fixture['customer'], $fixture['product']->id);

    expect(Wishlist::query()->where('customer_id', $fixture['customer']->id)->count())->toBe(1)
        ->and($item)->toBeInstanceOf(WishlistItem::class)
        ->and($service->check($fixture['customer'], $fixture['product'])['in_wishlist'])->toBeTrue()
        ->and($service->getWishlist($fixture['customer'])->items)->toHaveCount(1);

    $service->removeItem($fixture['customer'], $item);

    expect(WishlistItem::query()->count())->toBe(0)
        ->and($service->check($fixture['customer'], $fixture['product'])['in_wishlist'])->toBeFalse();
});

test('wishlist item add records analytics event', function (): void {
    $fixture = wishlistFixture();

    app(WishlistService::class)->addItem($fixture['customer'], $fixture['product']->id);

    expect(CommerceEvent::query()->where('event_name', 'wishlist.item_added')->count())->toBe(1);
});

test('wishlist item ownership is enforced on remove', function (): void {
    $fixture = wishlistFixture();
    $other = Customer::factory()->create();
    $service = app(WishlistService::class);

    $item = $service->addItem($fixture['customer'], $fixture['product']->id);

    expect(fn () => $service->removeItem($other, $item))
        ->toThrow(AccessDeniedHttpException::class);
});

test('adding out of stock product creates stock subscription and restock notifies once', function (): void {
    Notification::fake();

    $fixture = wishlistFixture(stock: 0);
    $service = app(WishlistService::class);

    $service->addItem($fixture['customer'], $fixture['product']->id);

    $subscription = ProductStockSubscription::query()->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->notified_at)->toBeNull();

    $warehouse = Warehouse::query()->firstOrFail();
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $fixture['product']);
    app(InventoryService::class)->adjust(
        $inventory,
        5,
        InventoryMovementType::Adjustment,
        'Restock',
    );

    Notification::assertSentTo($fixture['customer'], TemplatedNotification::class, function (TemplatedNotification $notification): bool {
        return $notification->payload->key === 'wishlist.back_in_stock';
    });

    expect($subscription->fresh()->notified_at)->not->toBeNull()
        ->and(NotificationTemplate::query()->where('key', 'wishlist.back_in_stock')->exists())->toBeTrue();
});
