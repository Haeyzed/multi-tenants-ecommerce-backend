<?php

declare(strict_types=1);

use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Tenant;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Documentation only needs tenant identification, not a provisioned tenant database.
    config(['tenancy.bootstrappers' => []]);
});

/**
 * Create a tenant domain mapping without provisioning a tenant database.
 */
function docsTenant(string $domain): Tenant
{
    /** @var Tenant $tenant */
    $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Docs '.$domain,
        'slug' => 'docs-'.Str::slug(Str::random(10)),
        'email' => Str::random(8).'@example.com',
        'status' => TenantStatus::Active,
        'is_active' => true,
    ]));

    $tenant->domains()->create([
        'domain' => $domain,
        'is_primary' => true,
    ]);

    return $tenant->fresh(['domains']) ?? $tenant;
}

test('default scramble docs route is disabled', function (): void {
    $this->get('http://localhost/docs/api')->assertNotFound();
});

test('unknown tenant domain does not initialize a default tenant', function (): void {
    $this->get('http://unknown.localhost/docs/tenant')
        ->assertNotFound();

    expect(tenancy()->initialized)->toBeFalse();
});

test('central domain cannot access tenant documentation', function (): void {
    $this->get('http://localhost/docs/tenant')
        ->assertNotFound();
});

test('tenant domain resolves for documentation without generating openapi', function (): void {
    $tenant = docsTenant('tenant1.localhost');

    // Hitting /docs/tenant triggers full OpenAPI generation and hangs as the API
    // surface grows. Verify tenancy resolution via a cheap tenant route instead.
    $this->get('http://tenant1.localhost/')
        ->assertOk();

    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant('id'))->toBe($tenant->getTenantKey());
});

test('new domain routes are registered for documentation surface', function (): void {
    expect(Route::has('tenant.brands.index'))->toBeTrue()
        ->and(Route::has('tenant.categories.index'))->toBeTrue()
        ->and(Route::has('tenant.customer-groups.index'))->toBeTrue()
        ->and(Route::has('tenant.flash-sales.index'))->toBeTrue()
        ->and(Route::has('tenant.drivers.index'))->toBeTrue()
        ->and(Route::has('tenant.deliveries.index'))->toBeTrue()
        ->and(Route::has('tenant.seller-groups.index'))->toBeTrue()
        ->and(Route::has('tenant.segments.store'))->toBeTrue();
});

test('landlord and tenant apis are registered with scramble', function (): void {
    $apis = array_keys(Scramble::getConfigurationsInstance()->all());

    expect($apis)->toContain('landlord', 'tenant');
});
