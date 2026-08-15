<?php

declare(strict_types=1);

use App\Contracts\Notification\SmsProvider;
use App\Services\Notification\Sms\NullSmsProvider;
use App\Services\Notification\Sms\Providers\AfricasTalkingSmsProvider;
use App\Services\Notification\Sms\Providers\AmazonSnsSmsProvider;
use App\Services\Notification\Sms\Providers\BulkSmsProvider;
use App\Services\Notification\Sms\Providers\HubtelSmsProvider;
use App\Services\Notification\Sms\Providers\MessageBirdSmsProvider;
use App\Services\Notification\Sms\Providers\TermiiSmsProvider;
use App\Services\Notification\Sms\Providers\TwilioSmsProvider;
use App\Services\Notification\Sms\Providers\VonageSmsProvider;
use App\Services\Notification\Sms\SmsManager;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'notifications.sms.enabled' => true,
        'notifications.sms.from' => 'Acme',
        'notifications.sms.timeout' => 5,
        'notifications.sms.connect_timeout' => 2,
    ]);
});

test('sms manager resolves all configured drivers', function (string $driver, string $class, string $name): void {
    $resolved = app(SmsManager::class)->driver($driver);

    expect($resolved)->toBeInstanceOf($class)
        ->and($resolved->name())->toBe($name);
})->with([
    ['null', NullSmsProvider::class, 'null'],
    ['twilio', TwilioSmsProvider::class, 'twilio'],
    ['vonage', VonageSmsProvider::class, 'vonage'],
    ['messagebird', MessageBirdSmsProvider::class, 'messagebird'],
    ['amazon_sns', AmazonSnsSmsProvider::class, 'amazon_sns'],
    ['sns', AmazonSnsSmsProvider::class, 'amazon_sns'],
    ['termii', TermiiSmsProvider::class, 'termii'],
    ['africastalking', AfricasTalkingSmsProvider::class, 'africastalking'],
    ['bulksms', BulkSmsProvider::class, 'bulksms'],
    ['hubtel', HubtelSmsProvider::class, 'hubtel'],
]);

test('container binds default sms provider from config', function (): void {
    config(['notifications.sms.default' => 'null']);

    expect(app(SmsProvider::class))->toBeInstanceOf(NullSmsProvider::class);
});

test('twilio provider sends via http client', function (): void {
    config([
        'notifications.sms.drivers.twilio' => [
            'account_sid' => 'ACtest',
            'auth_token' => 'secret',
            'from' => '+10000000000',
            'base_url' => 'https://api.twilio.com',
        ],
    ]);

    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
    ]);

    $result = app(TwilioSmsProvider::class)->send('+15551234567', 'Hello');

    expect($result['success'])->toBeTrue()
        ->and($result['message_id'])->toBe('SM123');

    Http::assertSentCount(1);
});

test('termii provider sends via http client', function (): void {
    config([
        'notifications.sms.drivers.termii' => [
            'api_key' => 'termii-key',
            'from' => 'Acme',
            'channel' => 'generic',
            'type' => 'plain',
            'base_url' => 'https://api.ng.termii.com',
        ],
    ]);

    Http::fake([
        'api.ng.termii.com/*' => Http::response(['message_id' => 'tm-1'], 200),
    ]);

    $result = app(TermiiSmsProvider::class)->send('+2348012345678', 'Hello Termii');

    expect($result['success'])->toBeTrue()
        ->and($result['message_id'])->toBe('tm-1');
});

test('vonage provider sends via http client', function (): void {
    config([
        'notifications.sms.drivers.vonage' => [
            'api_key' => 'key',
            'api_secret' => 'secret',
            'from' => 'Acme',
            'base_url' => 'https://rest.nexmo.com',
        ],
    ]);

    Http::fake([
        'rest.nexmo.com/*' => Http::response([
            'messages' => [
                ['status' => '0', 'message-id' => 'vonage-1'],
            ],
        ], 200),
    ]);

    $result = app(VonageSmsProvider::class)->send('+15551234567', 'Hello Vonage');

    expect($result['success'])->toBeTrue()
        ->and($result['message_id'])->toBe('vonage-1');
});

test('disabled sms provider does not send', function (): void {
    config([
        'notifications.sms.enabled' => false,
        'notifications.sms.drivers.twilio' => [
            'account_sid' => 'ACtest',
            'auth_token' => 'secret',
            'from' => '+10000000000',
        ],
    ]);

    Http::fake();

    $result = app(TwilioSmsProvider::class)->send('+15551234567', 'Hello');

    expect($result['success'])->toBeFalse();
    Http::assertNothingSent();
});
