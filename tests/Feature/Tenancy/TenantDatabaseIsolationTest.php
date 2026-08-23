<?php

declare(strict_types=1);

use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Tenancy\Bootstrappers\TenantPaymentConfigBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

uses(RefreshDatabase::class);

/**
 * Documents DB-per-tenant isolation invariants.
 *
 * Runtime dual-tenant Seller/HR/CONTENT isolation smoke:
 * tests/Feature/Tenancy/DualTenantIsolationSmokeTest.php
 *
 * Cross-tenant SaaS IDOR coverage for landlord subscription endpoints lives in
 * tests/Feature/Landlord/SaaS/SaaSFoundationTest.php
 * ("tenant a cannot access tenant b subscription endpoints").
 */
test('tenancy bootstrappers include database and payment config bootstrappers', function (): void {
    $bootstrappers = config('tenancy.bootstrappers');

    expect($bootstrappers)->toContain(DatabaseTenancyBootstrapper::class)
        ->and($bootstrappers)->toContain(TenantPaymentConfigBootstrapper::class);

    $databaseIndex = array_search(DatabaseTenancyBootstrapper::class, $bootstrappers, true);
    $paymentIndex = array_search(TenantPaymentConfigBootstrapper::class, $bootstrappers, true);

    expect($databaseIndex)->toBeInt()
        ->and($paymentIndex)->toBeInt()
        ->and($paymentIndex)->toBeGreaterThan($databaseIndex);
});

test('tenant commerce models do not use BelongsToTenant single-db trait', function (): void {
    foreach ([Product::class, Customer::class, Order::class] as $model) {
        $uses = class_uses_recursive($model);

        expect($uses)->not->toHaveKey(BelongsToTenant::class);
    }
});

test('products tenant migration has no tenant_id column', function (): void {
    $path = database_path('migrations/tenant/2026_08_15_034302_create_products_table.php');

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->not->toContain('tenant_id')
        ->and($contents)->toContain("Schema::create('products'");
});
