<?php

declare(strict_types=1);

use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Tenant;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

/**
 * @return list<string>
 */
function openApiPaths(array $document): array
{
    return array_keys($document['paths'] ?? []);
}

function pathsContain(array $paths, string $needle): bool
{
    foreach ($paths as $path) {
        if (str_contains((string) $path, $needle)) {
            return true;
        }
    }

    return false;
}

test('landlord documentation ui is available on the central domain', function (): void {
    $this->get('http://localhost/docs/landlord')
        ->assertOk();
});

test('landlord openapi contains landlord endpoints and excludes tenant endpoints', function (): void {
    $document = $this->getJson('http://localhost/docs/landlord/openapi.json')
        ->assertOk()
        ->json();

    expect($document['info']['title'] ?? null)->toBe('Multi-Tenants E-commerce API - Landlord');

    $paths = openApiPaths($document);

    expect(pathsContain($paths, 'tenants'))->toBeTrue()
        ->and(pathsContain($paths, 'plans'))->toBeTrue()
        ->and(pathsContain($paths, 'brands'))->toBeFalse()
        ->and(pathsContain($paths, 'categories'))->toBeFalse();
});

test('default scramble docs route is disabled', function (): void {
    $this->get('http://localhost/docs/api')->assertNotFound();
});

test('tenant documentation requires a resolved tenant domain', function (): void {
    $tenant = docsTenant('tenant1.localhost');

    $this->get('http://tenant1.localhost/docs/tenant')
        ->assertOk();

    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant('id'))->toBe($tenant->getTenantKey());
});

test('tenant documentation works for a second tenant domain', function (): void {
    $tenant = docsTenant('tenant2.localhost');

    $this->get('http://tenant2.localhost/docs/tenant')
        ->assertOk();

    expect(tenant('id'))->toBe($tenant->getTenantKey());
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

test('tenant openapi contains tenant endpoints and excludes landlord endpoints', function (): void {
    docsTenant('tenant-docs.localhost');

    $document = $this->getJson('http://tenant-docs.localhost/docs/tenant/openapi.json')
        ->assertOk()
        ->json();

    expect($document['info']['title'] ?? null)->toBe('Multi-Tenants E-commerce API - Tenant');

    $paths = openApiPaths($document);

    expect(pathsContain($paths, 'brands'))->toBeTrue()
        ->and(pathsContain($paths, 'categories'))->toBeTrue()
        ->and(pathsContain($paths, 'tenants'))->toBeFalse()
        ->and(pathsContain($paths, 'plans'))->toBeFalse();
});

test('landlord and tenant apis are registered with scramble', function (): void {
    $apis = array_keys(Scramble::getConfigurationsInstance()->all());

    expect($apis)->toContain('landlord', 'tenant');

    $landlord = app(Generator::class)(Scramble::getGeneratorConfig('landlord'));
    $tenant = app(Generator::class)(Scramble::getGeneratorConfig('tenant'));

    expect(pathsContain(openApiPaths($landlord), 'brands'))->toBeFalse()
        ->and(pathsContain(openApiPaths($tenant), 'tenants'))->toBeFalse()
        ->and(pathsContain(openApiPaths($tenant), 'brands'))->toBeTrue();
});
