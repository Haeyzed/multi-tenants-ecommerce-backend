<?php

declare(strict_types=1);

use App\DTO\Payment\PaymentInitiationRequest;
use App\Services\Payment\Gateways\FakePaymentGateway;
use App\Services\Payment\Gateways\FlutterwaveGateway;
use App\Services\Payment\Gateways\MoniepointGateway;
use App\Services\Payment\Gateways\MonnifyGateway;
use App\Services\Payment\Gateways\PaystackGateway;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\Webhooks\FlutterwavePaymentWebhookHandler;
use App\Services\Tenant\Commerce\OrderPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

test('payment manager resolves all registered drivers', function (): void {
    $manager = app(PaymentManager::class);

    expect($manager->drivers())->toBe(['paystack', 'flutterwave', 'monnify', 'moniepoint', 'fake'])
        ->and($manager->driver('paystack'))->toBeInstanceOf(PaystackGateway::class)
        ->and($manager->driver('flutterwave'))->toBeInstanceOf(FlutterwaveGateway::class)
        ->and($manager->driver('monnify'))->toBeInstanceOf(MonnifyGateway::class)
        ->and($manager->driver('moniepoint'))->toBeInstanceOf(MoniepointGateway::class)
        ->and($manager->driver('fake'))->toBeInstanceOf(FakePaymentGateway::class);
});

test('fake payment gateway initialize and verify are deterministic', function (): void {
    $gateway = app(FakePaymentGateway::class);

    $init = $gateway->initializePayment(new PaymentInitiationRequest(
        amount: '50.00',
        currency: 'NGN',
        email: 'buyer@example.com',
        reference: 'FAKE-OK-123',
    ));

    expect($init->authorizationUrl)->toContain('reference=FAKE-OK-123')
        ->and($init->provider)->toBe('fake')
        ->and($gateway->supportsCurrency('USD'))->toBeTrue()
        ->and($gateway->verifyPayment('FAKE-OK-123')->successful)->toBeTrue()
        ->and($gateway->verifyPayment('FAKE-FAIL-1')->successful)->toBeFalse()
        ->and($gateway->refundPaymentDetailed('txn_1', '10.00')->successful)->toBeTrue();
});

test('flutterwave initialize and verify use official endpoints', function (): void {
    config([
        'payment.drivers.flutterwave.secret_key' => 'flw_secret',
        'payment.drivers.flutterwave.callback_url' => 'https://shop.test/callback',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.flutterwave.com/v3/payments' => Http::response([
            'status' => 'success',
            'message' => 'Hosted Link',
            'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc'],
        ], 200),
        'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
            'status' => 'success',
            'message' => 'Transaction fetched successfully',
            'data' => [
                'id' => 3091255,
                'tx_ref' => 'ORD-FLW-1',
                'amount' => 200,
                'currency' => 'NGN',
                'status' => 'successful',
                'created_at' => '2026-08-16T10:00:00.000Z',
            ],
        ], 200),
    ]);

    $gateway = app(FlutterwaveGateway::class);

    $init = $gateway->initializePayment(new PaymentInitiationRequest(
        amount: '200.00',
        currency: 'NGN',
        email: 'buyer@example.com',
        reference: 'ORD-FLW-1',
        customerName: 'Buyer One',
    ));

    $verified = $gateway->verifyPayment('ORD-FLW-1');

    expect($init->authorizationUrl)->toBe('https://checkout.flutterwave.com/v3/hosted/pay/abc')
        ->and($verified->successful)->toBeTrue()
        ->and($verified->amount)->toBe('200.00')
        ->and($verified->providerTransactionId)->toBe('3091255');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.flutterwave.com/v3/payments'
        && $request['tx_ref'] === 'ORD-FLW-1');
});

test('monnify authenticates then initializes a transaction', function (): void {
    config([
        'payment.drivers.monnify.api_key' => 'MK_TEST_KEY',
        'payment.drivers.monnify.secret_key' => 'MK_TEST_SECRET',
        'payment.drivers.monnify.contract_code' => 'CONTRACT123',
        'payment.drivers.monnify.callback_url' => 'https://shop.test/monnify/callback',
    ]);

    Cache::flush();
    Http::preventStrayRequests();
    Http::fake([
        'sandbox.monnify.com/api/v1/auth/login' => Http::response([
            'requestSuccessful' => true,
            'responseBody' => [
                'accessToken' => 'monnify-token',
                'expiresIn' => 3600,
            ],
        ], 200),
        'sandbox.monnify.com/api/v1/merchant/transactions/init-transaction' => Http::response([
            'requestSuccessful' => true,
            'responseBody' => [
                'paymentReference' => 'ORD-MNF-1',
                'transactionReference' => 'MNFY|1',
                'checkoutUrl' => 'https://sandbox.monnify.com/checkout/1',
            ],
        ], 200),
    ]);

    $gateway = app(MonnifyGateway::class);

    $init = $gateway->initializePayment(new PaymentInitiationRequest(
        amount: '150.00',
        currency: 'NGN',
        email: 'buyer@example.com',
        reference: 'ORD-MNF-1',
        customerName: 'Buyer One',
    ));

    expect($init->authorizationUrl)->toBe('https://sandbox.monnify.com/checkout/1')
        ->and($init->accessCode)->toBe('MNFY|1');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/v1/auth/login'));
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/init-transaction')
        && $request['paymentReference'] === 'ORD-MNF-1'
        && $request['contractCode'] === 'CONTRACT123');
});

test('moniepoint scaffold fails clearly', function (): void {
    $gateway = app(MoniepointGateway::class);

    expect(fn () => $gateway->initializePayment(new PaymentInitiationRequest(
        amount: '10.00',
        currency: 'NGN',
        email: 'buyer@example.com',
        reference: 'ORD-MP-1',
    )))->toThrow(RuntimeException::class, 'Moniepoint live API not configured')
        ->and($gateway->verifyPayment('ORD-MP-1')->successful)->toBeFalse()
        ->and($gateway->refundPaymentDetailed('txn', '10.00')->successful)->toBeFalse()
        ->and($gateway->refundPaymentDetailed('txn', '10.00')->message)->toContain('Moniepoint live API not configured');
});

test('flutterwave webhook handler verifies hash and extracts reference', function (): void {
    config(['payment.drivers.flutterwave.secret_hash' => 'flw_hash_secret']);

    $handler = app(FlutterwavePaymentWebhookHandler::class);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'id' => 555,
            'tx_ref' => 'ORD-FLW-WH-1',
            'status' => 'successful',
        ],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    $valid = Request::create('/webhooks/flutterwave', 'POST', $payload, server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => 'flw_hash_secret',
    ], content: $raw);

    $invalid = Request::create('/webhooks/flutterwave', 'POST', $payload, server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => 'wrong',
    ], content: $raw);

    expect($handler->verifySignature($valid))->toBeTrue()
        ->and($handler->verifySignature($invalid))->toBeFalse()
        ->and($handler->isSuccessfulCharge($valid))->toBeTrue()
        ->and($handler->paymentReference($valid))->toBe('ORD-FLW-WH-1')
        ->and($handler->eventId($valid))->toBe('555');
});

test('flutterwave verified webhook claims are idempotent by event id', function (): void {
    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_16_071511_create_payment_webhook_events_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Schema::create('order_payments', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('order_id')->nullable();
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->decimal('amount', 14, 2)->default(0);
        $table->char('currency', 3)->default('NGN');
        $table->string('gateway')->default('flutterwave');
        $table->string('reference')->unique();
        $table->string('status')->default('pending');
        $table->timestamps();
    });

    $service = app(OrderPaymentService::class);

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'id' => 777001,
            'tx_ref' => 'MISSING-REF-FOR-IDEMPOTENCY',
            'status' => 'successful',
        ],
    ];

    $first = $service->handleVerifiedWebhook(
        provider: 'flutterwave',
        reference: 'MISSING-REF-FOR-IDEMPOTENCY',
        payload: $payload,
        eventId: '777001',
        eventType: 'charge.completed',
    );

    $second = $service->handleVerifiedWebhook(
        provider: 'flutterwave',
        reference: 'MISSING-REF-FOR-IDEMPOTENCY',
        payload: $payload,
        eventId: '777001',
        eventType: 'charge.completed',
    );

    expect($first['processed'])->toBeFalse()
        ->and($second['processed'])->toBeFalse()
        ->and($second['duplicate'] ?? false)->toBeTrue();
});
