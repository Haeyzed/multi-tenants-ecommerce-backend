<?php

declare(strict_types=1);

use App\Models\Tenant\Customer;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Order;
use App\Models\Tenant\User;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use ReflectionClass;

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

    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

function channelCallback(string $pattern): callable
{
    $broadcaster = Broadcast::driver();
    $reflection = new ReflectionClass($broadcaster);
    $property = $reflection->getProperty('channels');
    $channels = $property->getValue($broadcaster);

    expect($channels)->toHaveKey($pattern);

    return $channels[$pattern];
}

function authorizeChannel(string $pattern, mixed $user, int|string $id): bool
{
    $callback = channelCallback($pattern);
    $result = $callback($user, $id);

    return (bool) $result;
}

test('order channel requires staff permission or customer ownership', function (): void {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $otherCustomer = Customer::factory()->create();

    $staffWithoutPermission = User::factory()->create();
    $staffWithPermission = User::factory()->create();
    $staffWithPermission->givePermissionTo('orders.view');

    expect(authorizeChannel('order.{id}', $staffWithoutPermission, $order->id))->toBeFalse()
        ->and(authorizeChannel('order.{id}', $staffWithPermission, $order->id))->toBeTrue()
        ->and(authorizeChannel('order.{id}', $customer, $order->id))->toBeTrue()
        ->and(authorizeChannel('order.{id}', $otherCustomer, $order->id))->toBeFalse();
});

test('delivery channel allows staff with permission, assigned driver, and owning customer', function (): void {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $driver = Driver::factory()->create();
    $otherDriver = Driver::factory()->create();
    $delivery = Delivery::factory()->create([
        'order_id' => $order->id,
        'driver_id' => $driver->id,
    ]);

    $staff = User::factory()->create();
    $staff->givePermissionTo('deliveries.view');
    $denied = User::factory()->create();

    expect(authorizeChannel('delivery.{id}', $staff, $delivery->id))->toBeTrue()
        ->and(authorizeChannel('delivery.{id}', $denied, $delivery->id))->toBeFalse()
        ->and(authorizeChannel('delivery.{id}', $driver, $delivery->id))->toBeTrue()
        ->and(authorizeChannel('delivery.{id}', $otherDriver, $delivery->id))->toBeFalse()
        ->and(authorizeChannel('delivery.{id}', $customer, $delivery->id))->toBeTrue();
});

test('driver and customer channels enforce self or staff permission', function (): void {
    $driver = Driver::factory()->create();
    $otherDriver = Driver::factory()->create();
    $customer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $staffDrivers = User::factory()->create();
    $staffDrivers->givePermissionTo('drivers.show');
    $staffCustomers = User::factory()->create();
    $staffCustomers->givePermissionTo('customers.view');
    $denied = User::factory()->create();

    expect(authorizeChannel('driver.{id}', $driver, $driver->id))->toBeTrue()
        ->and(authorizeChannel('driver.{id}', $otherDriver, $driver->id))->toBeFalse()
        ->and(authorizeChannel('driver.{id}', $staffDrivers, $driver->id))->toBeTrue()
        ->and(authorizeChannel('driver.{id}', $denied, $driver->id))->toBeFalse()
        ->and(authorizeChannel('customer.{id}', $customer, $customer->id))->toBeTrue()
        ->and(authorizeChannel('customer.{id}', $otherCustomer, $customer->id))->toBeFalse()
        ->and(authorizeChannel('customer.{id}', $staffCustomers, $customer->id))->toBeTrue()
        ->and(authorizeChannel('customer.{id}', $denied, $customer->id))->toBeFalse();
});
