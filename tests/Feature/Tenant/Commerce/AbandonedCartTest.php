<?php

declare(strict_types=1);

use App\Enums\Tenant\Commerce\CartStatus;
use App\Jobs\MarkAbandonedCartsJob;
use App\Models\Landlord\NotificationTemplate;
use App\Models\Tenant\Cart;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Notifications\TemplatedNotification;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Database\Seeders\Landlord\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (abandonedCartMigrationFiles() as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed(NotificationTemplateSeeder::class);
    config(['notifications.queue' => false, 'notifications.sms.default' => 'null']);
});

/**
 * @return list<string>
 */
function abandonedCartMigrationFiles(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034315_create_product_prices_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_060003_create_carts_table.php',
        '2026_08_15_060004_create_cart_items_table.php',
        '2026_08_15_080010_add_abandoned_fields_to_carts_table.php',
    ];
}

test('mark abandoned carts job marks stale carts and notifies once', function (): void {
    Notification::fake();

    app(CommerceSettingService::class)->set('cart.abandon_after_hours', '1');

    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['allow_backorder' => true]);
    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => '50.00',
        'is_active' => true,
    ]);

    app(CartService::class)->addItem($customer, $product->id, null, 1);
    $cart = Cart::query()->where('customer_id', $customer->id)->first();
    $cart->updated_at = now()->subHours(2);
    $cart->save();

    expect(NotificationTemplate::query()->where('key', 'cart.abandoned')->exists())->toBeTrue();

    (new MarkAbandonedCartsJob)->handle(
        app(CommerceSettingService::class),
        app(NotificationService::class),
    );

    $cart->refresh();
    expect($cart->status)->toBe(CartStatus::Abandoned)
        ->and($cart->abandoned_at)->not->toBeNull()
        ->and($cart->abandoned_notified_at)->not->toBeNull();

    Notification::assertSentTo($customer, TemplatedNotification::class);
    $sentAfterFirst = count(Notification::sent($customer, TemplatedNotification::class));

    (new MarkAbandonedCartsJob)->handle(
        app(CommerceSettingService::class),
        app(NotificationService::class),
    );

    expect(count(Notification::sent($customer, TemplatedNotification::class)))->toBe($sentAfterFirst);
});
