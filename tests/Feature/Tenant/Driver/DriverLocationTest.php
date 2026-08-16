<?php

declare(strict_types=1);

use App\Events\DriverLocationUpdated;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use App\Models\Tenant\DriverLocation;
use App\Models\Tenant\Order;
use App\Services\Tenant\Delivery\DeliveryService;
use App\Services\Tenant\Driver\DriverLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'delivery.location.min_persist_seconds' => 5,
        'delivery.location.broadcast_throttle_seconds' => 4,
    ]);

    $migrationFiles = [
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060009_create_shipments_table.php',
        '2026_08_16_120000_create_drivers_table.php',
        '2026_08_16_120002_create_deliveries_table.php',
        '2026_08_16_120951_add_rejected_and_arrived_timestamps_to_deliveries_table.php',
        '2026_08_16_120003_create_driver_locations_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

test('location recording rejects wrong driver', function (): void {
    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $assigned = Driver::factory()->create();
    $other = Driver::factory()->create();
    $deliveryService = app(DeliveryService::class);
    $locationService = app(DriverLocationService::class);

    $delivery = $deliveryService->createForOrder($order);
    $delivery = $deliveryService->assign($delivery, $assigned);
    $delivery = $deliveryService->accept($delivery, $assigned);

    expect(fn () => $locationService->record($other, $delivery, [
        'latitude' => 6.5244,
        'longitude' => 3.3792,
    ]))->toThrow(ValidationException::class);
});

test('location recording rejects inactive delivery', function (): void {
    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $driver = Driver::factory()->create();
    $deliveryService = app(DeliveryService::class);
    $locationService = app(DriverLocationService::class);

    $delivery = $deliveryService->createForOrder($order);
    $delivery = $deliveryService->assign($delivery, $driver);

    expect(fn () => $locationService->record($driver, $delivery, [
        'latitude' => 6.5244,
        'longitude' => 3.3792,
    ]))->toThrow(ValidationException::class);
});

test('location recording persists for active delivery', function (): void {
    Event::fake([DriverLocationUpdated::class]);

    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $driver = Driver::factory()->create();
    $deliveryService = app(DeliveryService::class);
    $locationService = app(DriverLocationService::class);

    $delivery = $deliveryService->createForOrder($order);
    $delivery = $deliveryService->assign($delivery, $driver);
    $delivery = $deliveryService->accept($delivery, $driver);

    $location = $locationService->record($driver, $delivery, [
        'latitude' => 6.5244,
        'longitude' => 3.3792,
        'accuracy' => 12.5,
    ]);

    expect($location)->toBeInstanceOf(DriverLocation::class)
        ->and($location->driver_id)->toBe($driver->id)
        ->and($location->delivery_id)->toBe($delivery->id)
        ->and(DriverLocation::query()->count())->toBe(1);

    Event::assertDispatched(DriverLocationUpdated::class);

    $throttled = $locationService->record($driver, $delivery->fresh(), [
        'latitude' => 6.5250,
        'longitude' => 3.3800,
    ]);

    expect($throttled)->toBeNull()
        ->and(DriverLocation::query()->count())->toBe(1);
});
