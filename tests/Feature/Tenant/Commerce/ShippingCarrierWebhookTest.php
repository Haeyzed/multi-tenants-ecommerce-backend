<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Shipping\CarrierWebhookController;
use App\Models\Tenant\ShippingCarrierWebhookEvent;
use App\Services\Shipping\Carriers\FakeCarrier;
use App\Services\Shipping\ShippingCarrierManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);

    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_16_130010_create_shipping_carrier_webhook_events_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);
});

test('shipping carrier manager resolves fake and stub drivers as FakeCarrier', function (): void {
    $manager = app(ShippingCarrierManager::class);

    expect($manager->driver('fake'))->toBeInstanceOf(FakeCarrier::class)
        ->and($manager->driver('dhl'))->toBeInstanceOf(FakeCarrier::class)
        ->and($manager->driver('gig'))->toBeInstanceOf(FakeCarrier::class);
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
        ->and($firstData['data']['duplicate'])->toBeFalse();

    $secondRequest = Request::create('/api/webhooks/shipping/fake', 'POST', $payload);
    $second = $controller($secondRequest, 'fake');
    $secondData = $second->getData(true);

    expect($second->getStatusCode())->toBe(200)
        ->and($secondData['data']['stored'])->toBeFalse()
        ->and($secondData['data']['duplicate'])->toBeTrue()
        ->and(ShippingCarrierWebhookEvent::query()->count())->toBe(1)
        ->and(ShippingCarrierWebhookEvent::query()->first()->provider)->toBe('fake')
        ->and(ShippingCarrierWebhookEvent::query()->first()->event_id)->toBe('evt-123');
});
