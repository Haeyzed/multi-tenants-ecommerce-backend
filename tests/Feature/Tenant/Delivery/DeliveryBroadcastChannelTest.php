<?php

declare(strict_types=1);

use App\Events\DeliveryAssigned;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Order;
use App\Services\Tenant\Delivery\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

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

test('delivery assigned event broadcasts on expected channels', function (): void {
    Event::fake([DeliveryAssigned::class]);

    $order = Order::factory()->create(['customer_id' => Customer::factory()]);
    $driver = Driver::factory()->create();
    $delivery = app(DeliveryService::class)->createForOrder($order);
    $delivery = app(DeliveryService::class)->assign($delivery, $driver);

    Event::assertDispatched(DeliveryAssigned::class, function (DeliveryAssigned $event) use ($delivery, $driver): bool {
        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

        return $event->delivery->is($delivery)
            && in_array('private-delivery.'.$delivery->id, $channels, true)
            && in_array('private-order.'.$delivery->order_id, $channels, true)
            && in_array('private-driver.'.$driver->id, $channels, true)
            && $event->broadcastAs() === 'delivery.assigned'
            && $event->broadcastWith()['status'] === 'assigned';
    });
});
