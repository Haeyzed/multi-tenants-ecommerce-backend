<?php

declare(strict_types=1);

use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Enums\Tenant\Driver\DriverAvailability;
use App\Enums\Tenant\Driver\DriverStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Order;
use App\Services\Tenant\Delivery\Assignment\AutomaticDriverAssignmentStrategy;
use App\Services\Tenant\Delivery\Assignment\ManualDriverAssignmentStrategy;
use App\Services\Tenant\Delivery\DeliveryService;
use App\Services\Tenant\Delivery\DriverAssignmentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

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

    config(['delivery.assignment.strategy' => 'automatic']);
});

test('automatic strategy picks the first available driver by id', function (): void {
    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $busy = Driver::factory()->create(['availability' => DriverAvailability::Available]);
    $available = Driver::factory()->create(['availability' => DriverAvailability::Available]);

    Delivery::factory()->create([
        'order_id' => Order::factory()->create(['customer_id' => Customer::factory()]),
        'driver_id' => $busy->id,
        'status' => DeliveryStatus::Assigned,
        'assigned_at' => now(),
    ]);

    $delivery = Delivery::factory()->create([
        'order_id' => $order->id,
        'status' => DeliveryStatus::Pending,
    ]);

    $picked = app(AutomaticDriverAssignmentStrategy::class)->assign($delivery);

    expect($picked)->not->toBeNull()
        ->and($picked->id)->toBe($available->id)
        ->and($picked->id)->not->toBe($busy->id);
});

test('automatic strategy skips busy and unavailable drivers', function (): void {
    $order = Order::factory()->create(['customer_id' => Customer::factory()]);

    $inactive = Driver::factory()->inactive()->create();
    $unavailable = Driver::factory()->unavailable()->create();
    $onDelivery = Driver::factory()->create(['availability' => DriverAvailability::OnDelivery]);
    $busyAccepted = Driver::factory()->create();
    $free = Driver::factory()->create();

    Delivery::factory()->create([
        'order_id' => Order::factory()->create(['customer_id' => Customer::factory()]),
        'driver_id' => $busyAccepted->id,
        'status' => DeliveryStatus::Accepted,
        'assigned_at' => now(),
        'accepted_at' => now(),
    ]);

    $delivery = Delivery::factory()->create([
        'order_id' => $order->id,
        'status' => DeliveryStatus::Pending,
    ]);

    $picked = app(AutomaticDriverAssignmentStrategy::class)->assign($delivery);

    expect($picked)->not->toBeNull()
        ->and($picked->id)->toBe($free->id)
        ->and($picked->id)->not->toBe($inactive->id)
        ->and($picked->id)->not->toBe($unavailable->id)
        ->and($picked->id)->not->toBe($onDelivery->id)
        ->and($picked->id)->not->toBe($busyAccepted->id);
});

test('manual strategy always returns null', function (): void {
    $delivery = Delivery::factory()->create([
        'order_id' => Order::factory()->create(['customer_id' => Customer::factory()]),
    ]);

    expect(app(ManualDriverAssignmentStrategy::class)->assign($delivery))->toBeNull();
});

test('delivery service assignAutomatically uses manager and assigns driver', function (): void {
    config(['delivery.assignment.strategy' => 'automatic']);

    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $driver = Driver::factory()->create([
        'status' => DriverStatus::Active,
        'availability' => DriverAvailability::Available,
    ]);
    $service = app(DeliveryService::class);
    $delivery = $service->createForOrder($order);

    $assigned = $service->assignAutomatically($delivery);

    expect($assigned->status)->toBe(DeliveryStatus::Assigned)
        ->and($assigned->driver_id)->toBe($driver->id);
});

test('delivery service assignAutomatically throws when no driver available', function (): void {
    config(['delivery.assignment.strategy' => 'automatic']);

    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $service = app(DeliveryService::class);
    $delivery = $service->createForOrder($order);

    expect(fn () => $service->assignAutomatically($delivery))
        ->toThrow(ValidationException::class);
});

test('driver assignment manager resolves configured strategy', function (): void {
    config(['delivery.assignment.strategy' => 'manual']);
    expect(app(DriverAssignmentManager::class)->strategy())
        ->toBeInstanceOf(ManualDriverAssignmentStrategy::class);

    config(['delivery.assignment.strategy' => 'automatic']);
    expect(app(DriverAssignmentManager::class)->strategy())
        ->toBeInstanceOf(AutomaticDriverAssignmentStrategy::class);
});
