<?php

declare(strict_types=1);

use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\CustomerSegmentMember;
use App\Models\Tenant\Order;
use App\Services\Tenant\Customer\CustomerSegmentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach ([
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_090101_create_coupons_table.php',
        '2026_08_15_100402_create_customer_segments_table.php',
        '2026_08_16_110000_create_customer_groups_table.php',
        '2026_08_16_140000_create_customer_segment_members_table.php',
    ] as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

test('materialize writes membership rows and refreshes count', function (): void {
    $matching = Customer::factory()->create();
    Order::factory()->create([
        'customer_id' => $matching->id,
        'status' => OrderStatus::Confirmed,
        'grand_total' => '50.00',
        'placed_at' => now(),
    ]);
    Customer::factory()->create();

    $segment = CustomerSegment::factory()->rule(CustomerSegmentRule::ReturningCustomer)->create();
    $service = app(CustomerSegmentationService::class);

    $refreshed = $service->materialize($segment);

    expect($refreshed->membership_refreshed_at)->not->toBeNull()
        ->and($refreshed->customers_count)->toBe(1)
        ->and(CustomerSegmentMember::query()->where('customer_segment_id', $segment->id)->count())->toBe(1)
        ->and(CustomerSegmentMember::query()->where('customer_id', $matching->id)->exists())->toBeTrue();
});

test('matches prefers materialized membership over live evaluation', function (): void {
    $customer = Customer::factory()->create();
    $segment = CustomerSegment::factory()->rule(CustomerSegmentRule::ReturningCustomer)->create([
        'membership_refreshed_at' => now(),
        'customers_count' => 1,
    ]);

    CustomerSegmentMember::query()->create([
        'customer_segment_id' => $segment->id,
        'customer_id' => $customer->id,
        'entered_at' => now(),
    ]);

    $service = app(CustomerSegmentationService::class);

    // Live rule would be false (no orders), membership says true.
    expect($service->matchesLive($customer, $segment))->toBeFalse()
        ->and($service->matches($customer, $segment))->toBeTrue();
});

test('materialize removes stale membership rows', function (): void {
    $stillMatching = Customer::factory()->create();
    Order::factory()->create([
        'customer_id' => $stillMatching->id,
        'status' => OrderStatus::Confirmed,
        'grand_total' => '20.00',
        'placed_at' => now(),
    ]);

    $stale = Customer::factory()->create();
    $segment = CustomerSegment::factory()->rule(CustomerSegmentRule::ReturningCustomer)->create();

    CustomerSegmentMember::query()->create([
        'customer_segment_id' => $segment->id,
        'customer_id' => $stale->id,
        'entered_at' => now()->subDay(),
    ]);

    app(CustomerSegmentationService::class)->materialize($segment);

    expect(CustomerSegmentMember::query()->where('customer_id', $stale->id)->exists())->toBeFalse()
        ->and(CustomerSegmentMember::query()->where('customer_id', $stillMatching->id)->exists())->toBeTrue();
});
