<?php

declare(strict_types=1);

use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Order;
use App\Services\Tenant\Driver\DriverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrationFiles = [
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060009_create_shipments_table.php',
        '2026_08_16_120000_create_drivers_table.php',
        '2026_08_16_120002_create_deliveries_table.php',
        '2026_08_16_120951_add_rejected_and_arrived_timestamps_to_deliveries_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

test('driver delivery history paginates and filters by status', function (): void {
    $driver = Driver::factory()->create();
    $other = Driver::factory()->create();
    $order = Order::factory()->create(['customer_id' => Customer::factory()]);

    Delivery::factory()->create([
        'order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => DeliveryStatus::Delivered,
    ]);
    Delivery::factory()->create([
        'order_id' => Order::factory()->create(['customer_id' => Customer::factory()])->id,
        'driver_id' => $driver->id,
        'status' => DeliveryStatus::Assigned,
    ]);
    Delivery::factory()->create([
        'order_id' => Order::factory()->create(['customer_id' => Customer::factory()])->id,
        'driver_id' => $other->id,
        'status' => DeliveryStatus::Delivered,
    ]);

    $service = app(DriverService::class);

    $all = $service->deliveryHistory($driver);
    expect($all->total())->toBe(2);

    $delivered = $service->deliveryHistory($driver, ['status' => DeliveryStatus::Delivered->value]);
    expect($delivered->total())->toBe(1)
        ->and($delivered->first()->status)->toBe(DeliveryStatus::Delivered);
});

test('driver stats count deliveries by status', function (): void {
    $driver = Driver::factory()->create();

    Delivery::factory()->create([
        'order_id' => Order::factory()->create(['customer_id' => Customer::factory()])->id,
        'driver_id' => $driver->id,
        'status' => DeliveryStatus::Delivered,
    ]);
    Delivery::factory()->create([
        'order_id' => Order::factory()->create(['customer_id' => Customer::factory()])->id,
        'driver_id' => $driver->id,
        'status' => DeliveryStatus::Delivered,
    ]);
    Delivery::factory()->create([
        'order_id' => Order::factory()->create(['customer_id' => Customer::factory()])->id,
        'driver_id' => $driver->id,
        'status' => DeliveryStatus::Failed,
    ]);

    $stats = app(DriverService::class)->stats($driver);

    expect($stats['total'])->toBe(3)
        ->and($stats['by_status'][DeliveryStatus::Delivered->value])->toBe(2)
        ->and($stats['by_status'][DeliveryStatus::Failed->value])->toBe(1)
        ->and($stats['by_status'][DeliveryStatus::Pending->value])->toBe(0);
});
