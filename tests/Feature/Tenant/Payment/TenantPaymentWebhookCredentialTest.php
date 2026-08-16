<?php

declare(strict_types=1);

use App\Models\Landlord\Tenant;
use App\Models\Tenant\TenantPaymentGateway;
use App\Services\Payment\PaymentWebhookManager;
use App\Services\Payment\Webhooks\PaystackPaymentWebhookHandler;
use App\Tenancy\Bootstrappers\TenantPaymentConfigBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_16_124844_create_tenant_payment_gateways_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Config::set('payment.drivers.paystack.secret_key', 'sk_env_wrong');
    Config::set('payment.drivers.paystack.webhook_secret', 'whsec_env_wrong');
});

test('tenant paystack webhook verifies against bootstrapped tenant secret not env', function (): void {
    $tenantSecret = 'sk_tenant_webhook_secret';

    TenantPaymentGateway::query()->create([
        'gateway' => 'paystack',
        'is_enabled' => true,
        'credentials' => [
            'secret_key' => $tenantSecret,
        ],
        'sort_order' => 0,
    ]);

    $bootstrapper = app(TenantPaymentConfigBootstrapper::class);
    $tenant = new Tenant;
    $tenant->id = (string) Str::uuid();
    $bootstrapper->bootstrap($tenant);

    expect(config('payment.drivers.paystack.webhook_secret'))->toBe($tenantSecret)
        ->and(config('payment.drivers.paystack.secret_key'))->toBe($tenantSecret);

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'id' => 1001,
            'reference' => 'ord_ref_tenant_1',
        ],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $validSignature = hash_hmac('sha512', $raw, $tenantSecret);
    $envSignature = hash_hmac('sha512', $raw, 'whsec_env_wrong');

    $validRequest = Request::create('/api/payments/webhooks/paystack', 'POST', $payload, server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $validSignature,
    ], content: $raw);

    $envRequest = Request::create('/api/payments/webhooks/paystack', 'POST', $payload, server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $envSignature,
    ], content: $raw);

    $handler = app(PaystackPaymentWebhookHandler::class);

    expect($handler->verifySignature($validRequest))->toBeTrue()
        ->and($handler->verifySignature($envRequest))->toBeFalse();

    $bootstrapper->revert();
});

test('moniepoint webhook endpoint rejects signatures while scaffolded', function (): void {
    $request = Request::create('/api/payments/webhooks/moniepoint', 'POST', [
        'event' => 'charge.success',
    ]);

    expect(fn () => app(PaymentWebhookManager::class)->handle('moniepoint', $request))
        ->toThrow(AccessDeniedHttpException::class);
});
