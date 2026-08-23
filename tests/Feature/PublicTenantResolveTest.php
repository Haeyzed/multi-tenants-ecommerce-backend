<?php

declare(strict_types=1);

use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function makePublicResolveTenant(array $overrides = []): Tenant
{
    /** @var Tenant $tenant */
    $tenant = Tenant::withoutEvents(function () use ($overrides): Tenant {
        return Tenant::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'ABC Store',
            'slug' => 'abc-'.uniqid(),
            'email' => 'owner@abc.test',
            'phone' => null,
            'status' => TenantStatus::Active,
            'is_active' => true,
        ], $overrides));
    });

    Domain::query()->create([
        'domain' => ($tenant->slug).'.example.test',
        'tenant_id' => $tenant->getTenantKey(),
        'is_primary' => true,
    ]);

    TenantProfile::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'display_name' => 'ABC Storefront',
        'slug' => $tenant->slug,
        'description' => 'Welcome to ABC',
        'is_public' => false,
        'email' => $tenant->email,
    ]);

    return $tenant->fresh(['domains', 'profile']);
}

test('public tenant can be resolved by domain query without authentication', function (): void {
    $tenant = makePublicResolveTenant();
    $domain = $tenant->domains->first()->domain;

    $this->getJson('/api/public/tenant?domain='.$domain)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', (string) $tenant->getTenantKey())
        ->assertJsonPath('data.display_name', 'ABC Storefront')
        ->assertJsonPath('data.allows_login', true)
        ->assertJsonPath('data.domain', $domain)
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.data');
});

test('public tenant resolve returns not found for unknown domain', function (): void {
    $this->getJson('/api/public/tenant?domain=unknown.example.test')
        ->assertNotFound();
});

test('inactive tenant resolves but does not allow login', function (): void {
    $tenant = makePublicResolveTenant([
        'status' => TenantStatus::Suspended,
        'is_active' => false,
    ]);
    $domain = $tenant->domains->first()->domain;

    $this->getJson('/api/public/tenant?domain='.$domain)
        ->assertOk()
        ->assertJsonPath('data.allows_login', false)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.status', 'suspended');
});

test('central domains cannot resolve as a tenant', function (): void {
    $central = config('tenancy.identification.central_domains.0');

    $this->getJson('/api/public/tenant?domain='.$central)
        ->assertNotFound();
});
