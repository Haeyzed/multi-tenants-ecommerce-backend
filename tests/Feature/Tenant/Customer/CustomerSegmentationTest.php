<?php

declare(strict_types=1);

use App\Enums\Tenant\Commerce\CartStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Models\Tenant\Cart;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Wishlist;
use App\Models\Tenant\WishlistItem;
use App\Services\Tenant\Customer\CustomerSegmentationService;
use Database\Seeders\Tenant\CustomerSegmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (customerSegmentationMigrationFiles() as $file) {
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
function customerSegmentationMigrationFiles(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060003_create_carts_table.php',
        '2026_08_15_060004_create_cart_items_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_080010_add_abandoned_fields_to_carts_table.php',
        '2026_08_15_090200_create_wishlists_table.php',
        '2026_08_15_090201_create_wishlist_items_table.php',
        '2026_08_15_100402_create_customer_segments_table.php',
        '2026_08_16_140000_create_customer_segment_members_table.php',
    ];
}

function segmentFor(CustomerSegmentRule $rule, int|string|null $value = null): CustomerSegment
{
    return CustomerSegment::factory()->rule($rule, $value)->create();
}

/**
 * Place a non-cancelled order of the given total for a customer.
 */
function placeOrderFor(Customer $customer, string $grandTotal, ?string $placedAt = null): Order
{
    return Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::Confirmed,
        'subtotal' => $grandTotal,
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'shipping_total' => '0.00',
        'grand_total' => $grandTotal,
        'placed_at' => $placedAt ?? now(),
    ]);
}

test('new and returning customer rules split on order history', function (): void {
    $withoutOrders = Customer::factory()->create();
    $withOrders = Customer::factory()->create();
    placeOrderFor($withOrders, '50.00');

    $service = app(CustomerSegmentationService::class);
    $newSegment = segmentFor(CustomerSegmentRule::NewCustomer);
    $returningSegment = segmentFor(CustomerSegmentRule::ReturningCustomer);

    expect($service->matches($withoutOrders, $newSegment))->toBeTrue()
        ->and($service->matches($withOrders, $newSegment))->toBeFalse()
        ->and($service->matches($withOrders, $returningSegment))->toBeTrue()
        ->and($service->matches($withoutOrders, $returningSegment))->toBeFalse();
});

test('cancelled orders do not make a customer returning', function (): void {
    $customer = Customer::factory()->create();

    Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::Cancelled,
        'cancelled_at' => now(),
    ]);

    $service = app(CustomerSegmentationService::class);

    expect($service->matches($customer, segmentFor(CustomerSegmentRule::NewCustomer)))->toBeTrue();
});

test('high value rule sums lifetime spend against the threshold', function (): void {
    $bigSpender = Customer::factory()->create();
    placeOrderFor($bigSpender, '600.00');
    placeOrderFor($bigSpender, '600.00');

    $smallSpender = Customer::factory()->create();
    placeOrderFor($smallSpender, '100.00');

    $segment = segmentFor(CustomerSegmentRule::HighValue, '1000.00');
    $service = app(CustomerSegmentationService::class);

    expect($service->matches($bigSpender, $segment))->toBeTrue()
        ->and($service->matches($smallSpender, $segment))->toBeFalse()
        ->and($service->count($segment))->toBe(1);
});

test('frequent buyer rule counts non cancelled orders', function (): void {
    $frequent = Customer::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        placeOrderFor($frequent, '10.00');
    }

    $occasional = Customer::factory()->create();
    placeOrderFor($occasional, '10.00');

    $segment = segmentFor(CustomerSegmentRule::FrequentBuyer, 3);
    $service = app(CustomerSegmentationService::class);

    expect($service->matches($frequent, $segment))->toBeTrue()
        ->and($service->matches($occasional, $segment))->toBeFalse();
});

test('inactive rule needs past orders and no recent activity', function (): void {
    $lapsed = Customer::factory()->create();
    placeOrderFor($lapsed, '10.00', now()->subDays(120)->toDateTimeString());

    $active = Customer::factory()->create();
    placeOrderFor($active, '10.00');

    $neverOrdered = Customer::factory()->create();

    $segment = segmentFor(CustomerSegmentRule::Inactive, 90);
    $service = app(CustomerSegmentationService::class);

    expect($service->matches($lapsed, $segment))->toBeTrue()
        ->and($service->matches($active, $segment))->toBeFalse()
        ->and($service->matches($neverOrdered, $segment))->toBeFalse();
});

test('wishlist and abandoned cart rules read commerce state', function (): void {
    $wishlistCustomer = Customer::factory()->create();
    $wishlist = Wishlist::query()->create(['customer_id' => $wishlistCustomer->id]);
    WishlistItem::query()->create([
        'wishlist_id' => $wishlist->id,
        'product_id' => Product::factory()->active()->create()->id,
    ]);

    $abandonedCartCustomer = Customer::factory()->create();
    Cart::query()->create([
        'customer_id' => $abandonedCartCustomer->id,
        'currency' => 'NGN',
        'status' => CartStatus::Abandoned,
    ]);

    $emptyCustomer = Customer::factory()->create();

    $service = app(CustomerSegmentationService::class);
    $wishlistSegment = segmentFor(CustomerSegmentRule::WishlistCustomer);
    $cartSegment = segmentFor(CustomerSegmentRule::AbandonedCartCustomer);

    expect($service->matches($wishlistCustomer, $wishlistSegment))->toBeTrue()
        ->and($service->matches($emptyCustomer, $wishlistSegment))->toBeFalse()
        ->and($service->matches($abandonedCartCustomer, $cartSegment))->toBeTrue()
        ->and($service->matches($emptyCustomer, $cartSegment))->toBeFalse();
});

test('multiple conditions combine with all and any match modes', function (): void {
    $customer = Customer::factory()->create();
    placeOrderFor($customer, '2000.00');

    $conditions = [
        ['type' => CustomerSegmentRule::ReturningCustomer->value],
        ['type' => CustomerSegmentRule::WishlistCustomer->value],
    ];

    $all = CustomerSegment::factory()->create([
        'rules' => ['match' => 'all', 'conditions' => $conditions],
    ]);

    $any = CustomerSegment::factory()->create([
        'rules' => ['match' => 'any', 'conditions' => $conditions],
    ]);

    $service = app(CustomerSegmentationService::class);

    expect($service->matches($customer, $all))->toBeFalse()
        ->and($service->matches($customer, $any))->toBeTrue();
});

test('evaluate returns every active matching segment slug', function (): void {
    $this->seed(CustomerSegmentSeeder::class);

    $customer = Customer::factory()->create();
    placeOrderFor($customer, '1500.00');

    $slugs = app(CustomerSegmentationService::class)->evaluate($customer);

    expect($slugs)->toContain('returning-customers')
        ->and($slugs)->toContain('high-value-customers')
        ->and($slugs)->not->toContain('new-customers');
});

test('segment customers are paginated', function (): void {
    Customer::factory()->count(3)->create()->each(
        fn (Customer $customer) => placeOrderFor($customer, '10.00'),
    );
    Customer::factory()->create();

    $segment = segmentFor(CustomerSegmentRule::ReturningCustomer);
    $paginator = app(CustomerSegmentationService::class)->customers($segment, ['per_page' => 2]);

    expect($paginator->total())->toBe(3)
        ->and($paginator->items())->toHaveCount(2);
});
