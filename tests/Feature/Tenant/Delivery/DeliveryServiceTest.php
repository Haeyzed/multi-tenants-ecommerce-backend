<?php

declare(strict_types=1);

use App\Enums\Tenant\Delivery\DeliveryStatus;
use App\Enums\Tenant\Driver\DriverAvailability;
use App\Events\DeliveryAccepted;
use App\Events\DeliveryAssigned;
use App\Events\DeliveryCompleted;
use App\Events\DeliveryStarted;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Order;
use App\Services\Tenant\Delivery\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

test('delivery status transitions follow the machine', function (): void {
    Event::fake([
        DeliveryAssigned::class,
        DeliveryAccepted::class,
        DeliveryStarted::class,
        DeliveryCompleted::class,
    ]);

    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $driver = Driver::factory()->create();
    $service = app(DeliveryService::class);

    $delivery = $service->createForOrder($order);

    expect($delivery->status)->toBe(DeliveryStatus::Pending);

    $delivery = $service->assign($delivery, $driver);
    expect($delivery->status)->toBe(DeliveryStatus::Assigned)
        ->and($delivery->driver_id)->toBe($driver->id);
    Event::assertDispatched(DeliveryAssigned::class);

    $delivery = $service->accept($delivery, $driver);
    expect($delivery->status)->toBe(DeliveryStatus::Accepted)
        ->and($driver->fresh()->availability)->toBe(DriverAvailability::OnDelivery);
    Event::assertDispatched(DeliveryAccepted::class);

    $delivery = $service->markPickedUp($delivery, $driver);
    expect($delivery->status)->toBe(DeliveryStatus::PickedUp)
        ->and($delivery->picked_up_at)->not->toBeNull();
    Event::assertDispatched(DeliveryStarted::class);

    $delivery = $service->markOutForDelivery($delivery, $driver);
    expect($delivery->status)->toBe(DeliveryStatus::OutForDelivery);

    $delivery = $service->markDelivered($delivery, $driver);
    expect($delivery->status)->toBe(DeliveryStatus::Delivered)
        ->and($driver->fresh()->availability)->toBe(DriverAvailability::Available);
    Event::assertDispatched(DeliveryCompleted::class);
});

test('invalid delivery transition throws validation exception', function (): void {
    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $driver = Driver::factory()->create();
    $service = app(DeliveryService::class);

    $delivery = $service->createForOrder($order);

    expect(fn () => $service->accept($delivery, $driver))
        ->toThrow(ValidationException::class);

    $delivery = $service->assign($delivery, $driver);

    expect(fn () => $service->markPickedUp($delivery, $driver))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->markDelivered($delivery, $driver))
        ->toThrow(ValidationException::class);
});

test('driver can reject assignment and pending can cancel', function (): void {
    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $driver = Driver::factory()->create();
    $service = app(DeliveryService::class);

    $delivery = $service->createForOrder($order);
    $delivery = $service->assign($delivery, $driver);
    $delivery = $service->reject($delivery, $driver);

    expect($delivery->status)->toBe(DeliveryStatus::Pending)
        ->and($delivery->driver_id)->toBeNull();

    $cancelled = $service->cancel($delivery);

    expect($cancelled->status)->toBe(DeliveryStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

test('wrong driver cannot accept delivery', function (): void {
    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $assigned = Driver::factory()->create();
    $other = Driver::factory()->create();
    $service = app(DeliveryService::class);

    $delivery = $service->createForOrder($order);
    $delivery = $service->assign($delivery, $assigned);

    expect(fn () => $service->accept($delivery, $other))
        ->toThrow(ValidationException::class);
});
