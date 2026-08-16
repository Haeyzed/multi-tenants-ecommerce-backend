<?php

declare(strict_types=1);

use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Exceptions\Shipping\UnsupportedShippingCarrierException;
use App\Http\Controllers\Tenant\Shipping\CarrierWebhookController;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShippingCarrierWebhookEvent;
use App\Services\Shipping\Carriers\FakeCarrier;
use App\Services\Shipping\CarrierWebhookManager;
use App\Services\Shipping\ShippingCarrierManager;
use App\Services\Shipping\Webhooks\FakeCarrierWebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
        'shipping.drivers.fake.webhook_secret' => null,
    ]);

    foreach (shippingCarrierWebhookMigrationFiles() as $file) {
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
function shippingCarrierWebhookMigrationFiles(): array
{
    return [
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_041028_create_customer_addresses_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060009_create_shipments_table.php',
        '2026_08_16_130010_create_shipping_carrier_webhook_events_table.php',
    ];
}

test('shipping carrier manager resolves fake and stub drivers as FakeCarrier', function (): void {
    $manager = app(ShippingCarrierManager::class);

    expect($manager->driver('fake'))->toBeInstanceOf(FakeCarrier::class)
        ->and($manager->driver('dhl'))->toBeInstanceOf(FakeCarrier::class)
        ->and($manager->driver('gig'))->toBeInstanceOf(FakeCarrier::class)
        ->and($manager->driver('local'))->toBeInstanceOf(FakeCarrier::class);
});

test('carrier webhook manager throws for unsupported processor', function (): void {
    $manager = app(CarrierWebhookManager::class);

    expect(fn () => $manager->processor('unknown-carrier'))
        ->toThrow(UnsupportedShippingCarrierException::class);
});

test('shipping webhook stores event once for identical event id', function (): void {
    $controller = app(CarrierWebhookController::class);
    $payload = [
        'event_id' => 'evt-123',
        'status' => 'in_transit',
        'tracking_number' => 'FAKE-ABC',
    ];

    $firstRequest = Request::create('/api/webhooks/shipping/fake', 'POST', $payload);
    $first = $controller($firstRequest, 'fake');
    $firstData = $first->getData(true);

    expect($first->getStatusCode())->toBe(200)
        ->and($firstData['data']['stored'])->toBeTrue()
        ->and($firstData['data']['duplicate'])->toBeFalse()
        ->and($firstData['data']['processed'])->toBeTrue();

    $secondRequest = Request::create('/api/webhooks/shipping/fake', 'POST', $payload);
    $second = $controller($secondRequest, 'fake');
    $secondData = $second->getData(true);

    expect($second->getStatusCode())->toBe(200)
        ->and($secondData['data']['stored'])->toBeFalse()
        ->and($secondData['data']['duplicate'])->toBeTrue()
        ->and($secondData['data']['processed'])->toBeFalse()
        ->and(ShippingCarrierWebhookEvent::query()->count())->toBe(1)
        ->and(ShippingCarrierWebhookEvent::query()->first()->provider)->toBe('fake')
        ->and(ShippingCarrierWebhookEvent::query()->first()->event_id)->toBe('evt-123');
});

test('carrier webhook manager is idempotent with fake processor', function (): void {
    $manager = app(CarrierWebhookManager::class);

    expect($manager->processor('fake'))->toBeInstanceOf(FakeCarrierWebhookProcessor::class);

    $payload = [
        'event_id' => 'mgr-evt-1',
        'status' => 'delivered',
        'tracking_number' => 'FAKE-XYZ',
        'occurred_at' => '2026-08-16T12:00:00+00:00',
    ];

    $request = Request::create('/api/webhooks/shipping/fake', 'POST', $payload);

    $first = $manager->handle('fake', $request);
    expect($first['stored'])->toBeTrue()
        ->and($first['duplicate'])->toBeFalse()
        ->and($first['processed'])->toBeTrue()
        ->and($first['shipment_updated'])->toBeFalse()
        ->and($first['shipment_id'])->toBeNull()
        ->and($first['normalized']->status)->toBe('delivered')
        ->and($first['normalized']->trackingNumber)->toBe('FAKE-XYZ')
        ->and($first['event'])->not->toBeNull()
        ->and($first['event']->processed_at)->not->toBeNull();

    $second = $manager->handle('fake', Request::create('/api/webhooks/shipping/fake', 'POST', $payload));
    expect($second['stored'])->toBeFalse()
        ->and($second['duplicate'])->toBeTrue()
        ->and($second['processed'])->toBeFalse()
        ->and($second['shipment_updated'])->toBeFalse()
        ->and(ShippingCarrierWebhookEvent::query()->count())->toBe(1);
});

test('fake webhook rejects bad signature when secret configured', function (): void {
    config(['shipping.drivers.fake.webhook_secret' => 'test-secret']);

    $processor = app(FakeCarrierWebhookProcessor::class);
    $body = json_encode(['event_id' => 'sig-bad', 'status' => 'delivered', 'tracking_number' => 'FAKE-1'], JSON_THROW_ON_ERROR);

    $missing = Request::create('/api/webhooks/shipping/fake', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $body);
    expect($processor->verify($missing))->toBeFalse();

    $wrong = Request::create('/api/webhooks/shipping/fake', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => 'deadbeef',
    ], $body);
    expect($processor->verify($wrong))->toBeFalse();

    $manager = app(CarrierWebhookManager::class);
    expect(fn () => $manager->handle('fake', $wrong))
        ->toThrow(AccessDeniedHttpException::class);
});

test('fake webhook accepts valid HMAC when secret configured', function (): void {
    $secret = 'test-secret';
    config(['shipping.drivers.fake.webhook_secret' => $secret]);

    $payload = [
        'event_id' => 'sig-ok',
        'status' => 'in_transit',
        'tracking_number' => 'FAKE-HMAC',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $body, $secret);

    $request = Request::create(
        '/api/webhooks/shipping/fake',
        'POST',
        $payload,
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => $signature,
        ],
        $body,
    );

    $processor = app(FakeCarrierWebhookProcessor::class);
    expect($processor->verify($request))->toBeTrue();

    $result = app(CarrierWebhookManager::class)->handle('fake', $request);
    expect($result['stored'])->toBeTrue()
        ->and($result['duplicate'])->toBeFalse()
        ->and($result['processed'])->toBeTrue();
});

test('webhook with tracking updates matching shipment status to delivered', function (): void {
    $order = Order::factory()->create();
    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'tracking_number' => 'FAKE-DELIVER-ME',
        'carrier' => 'fake',
        'status' => ShipmentStatus::InTransit,
    ]);

    $payload = [
        'event_id' => 'deliver-evt-1',
        'status' => 'delivered',
        'tracking_number' => 'FAKE-DELIVER-ME',
    ];

    $result = app(CarrierWebhookManager::class)->handle(
        'fake',
        Request::create('/api/webhooks/shipping/fake', 'POST', $payload),
    );

    expect($result['stored'])->toBeTrue()
        ->and($result['shipment_updated'])->toBeTrue()
        ->and($result['shipment_id'])->toBe($shipment->id)
        ->and($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->fresh()->delivered_at)->not->toBeNull();
});
