<?php

declare(strict_types=1);

use App\Models\Landlord\Tenant;
use App\Models\Tenant\CommerceSetting;
use App\Models\Tenant\TenantPaymentGateway;
use App\Services\Tenant\Payment\TenantPaymentGatewayService;
use App\Tenancy\Bootstrappers\TenantPaymentConfigBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_16_124844_create_tenant_payment_gateways_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_15_060001_create_commerce_settings_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Config::set('payment.default', 'paystack');
    Config::set('payment.drivers.paystack.secret_key', 'sk_env_fallback');
    Config::set('payment.drivers.paystack.public_key', 'pk_env_fallback');
    Config::set('payment.drivers.paystack.base_url', 'https://api.paystack.co');
    Config::set('payment.drivers.paystack.timeout', 15);
});

/**
 * Minimal tenant stand-in for bootstrapper tests (no DB provisioning).
 */
function paymentBootstrapTenant(): Tenant
{
    $tenant = new Tenant;
    $tenant->id = (string) Str::uuid();
    $tenant->exists = false;

    return $tenant;
}

test('payment bootstrapper merges tenant credentials without wiping unrelated driver keys', function (): void {
    TenantPaymentGateway::query()->create([
        'gateway' => 'paystack',
        'is_enabled' => true,
        'credentials' => [
            'secret_key' => 'sk_tenant_secret',
            'public_key' => 'pk_tenant_public',
        ],
        'sort_order' => 0,
    ]);

    CommerceSetting::query()->create([
        'key' => 'payment.default_gateway',
        'value' => 'paystack',
    ]);

    $bootstrapper = app(TenantPaymentConfigBootstrapper::class);
    $bootstrapper->bootstrap(paymentBootstrapTenant());

    expect(config('payment.drivers.paystack.secret_key'))->toBe('sk_tenant_secret')
        ->and(config('payment.drivers.paystack.public_key'))->toBe('pk_tenant_public')
        ->and(config('payment.drivers.paystack.base_url'))->toBe('https://api.paystack.co')
        ->and((int) config('payment.drivers.paystack.timeout'))->toBe(15)
        ->and(config('payment.default'))->toBe('paystack');

    $bootstrapper->revert();

    expect(config('payment.drivers.paystack.secret_key'))->toBe('sk_env_fallback')
        ->and(config('payment.drivers.paystack.public_key'))->toBe('pk_env_fallback');
});

test('payment bootstrapper keeps env fallback when no gateway row exists', function (): void {
    $bootstrapper = app(TenantPaymentConfigBootstrapper::class);
    $bootstrapper->bootstrap(paymentBootstrapTenant());

    expect(config('payment.drivers.paystack.secret_key'))->toBe('sk_env_fallback')
        ->and(config('payment.drivers.paystack.public_key'))->toBe('pk_env_fallback')
        ->and(config('payment.drivers.paystack.base_url'))->toBe('https://api.paystack.co');

    $bootstrapper->revert();

    expect(config('payment.drivers.paystack.secret_key'))->toBe('sk_env_fallback');
});

test('credentialsFor returns decrypted tenant credentials', function (): void {
    TenantPaymentGateway::query()->create([
        'gateway' => 'paystack',
        'is_enabled' => true,
        'credentials' => [
            'secret_key' => 'sk_from_service',
        ],
        'sort_order' => 0,
    ]);

    $credentials = app(TenantPaymentGatewayService::class)
        ->credentialsFor('paystack');

    expect($credentials['secret_key'])->toBe('sk_from_service');
});
