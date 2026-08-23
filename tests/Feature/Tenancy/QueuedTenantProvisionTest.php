<?php

declare(strict_types=1);

use App\Enums\Landlord\TenantStatus;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Models\Tenant\User;
use App\Services\Landlord\Tenant\TenantService;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);

    config([
        'tenancy.database.prefix' => 'testing_tenant_',
        'tenancy.database.suffix' => '',
        'tenancy.identification.central_domains' => ['central.test', 'localhost', '127.0.0.1'],
        'tenancy.bootstrappers' => array_values(array_filter(
            config('tenancy.bootstrappers'),
            static fn (string $bootstrapper): bool => ! str_contains($bootstrapper, 'CacheTenancyBootstrapper'),
        )),
    ]);
});

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    foreach (File::glob(database_path('testing_tenant_*')) ?: [] as $database) {
        @File::delete($database);
    }
});

test('queued tenant create finishes with admin and leaves pending status', function (): void {
    $suffix = Str::lower(Str::random(8));
    $domain = "queued-{$suffix}.test";

    $tenant = app(TenantService::class)->store([
        'name' => 'Queued Store',
        'slug' => "queued-{$suffix}",
        'email' => "owner-{$suffix}@example.com",
        'status' => TenantStatus::Active->value,
        'is_active' => true,
        'domain' => $domain,
        'admin' => [
            'first_name' => 'Ada',
            'last_name' => 'Admin',
            'email' => "admin-{$suffix}@example.com",
            'password' => 'Password1!',
        ],
        'profile' => [
            'display_name' => 'Queued Store',
            'is_public' => true,
        ],
    ]);

    $tenant->refresh();

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->pending_provision)->toBeNull()
        ->and($tenant->domains()->where('domain', $domain)->exists())->toBeTrue();

    $tenant->run(function () use ($suffix): void {
        expect(User::query()->where('email', "admin-{$suffix}@example.com")->exists())->toBeTrue();
    });
});

test('central host with X-Tenant-Domain initializes tenancy', function (): void {
    $suffix = Str::lower(Str::random(8));
    $domain = "header-{$suffix}.test";

    $tenant = app(TenantService::class)->store([
        'name' => 'Header Store',
        'slug' => "header-{$suffix}",
        'email' => "owner-{$suffix}@example.com",
        'domain' => $domain,
        'admin' => [
            'first_name' => 'Ada',
            'last_name' => 'Admin',
            'email' => "admin-{$suffix}@example.com",
            'password' => 'Password1!',
        ],
    ]);

    $tenant->refresh();
    expect($tenant->status)->toBe(TenantStatus::Active);

    $this->get('http://central.test/', [
        InitializeTenancyByDomainOrHeader::HEADER => $domain,
    ])->assertOk();

    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant('id'))->toBe($tenant->getTenantKey());

    tenancy()->end();
});

test('central host with X-Tenant-Domain uses tenant auth not landlord', function (): void {
    $suffix = Str::lower(Str::random(8));
    $domain = "auth-header-{$suffix}.test";
    $email = "admin-{$suffix}@example.com";
    $password = 'Password1!';

    $tenant = app(TenantService::class)->store([
        'name' => 'Auth Header Store',
        'slug' => "auth-header-{$suffix}",
        'email' => "owner-{$suffix}@example.com",
        'domain' => $domain,
        'admin' => [
            'first_name' => 'Ada',
            'last_name' => 'Admin',
            'email' => $email,
            'password' => $password,
        ],
    ]);

    $tenant->refresh();
    expect($tenant->status)->toBe(TenantStatus::Active);

    $response = $this->postJson('http://central.test/api/auth/login', [
        'email' => $email,
        'password' => $password,
    ], [
        InitializeTenancyByDomainOrHeader::HEADER => $domain,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', $email);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();

    if (tenancy()->initialized) {
        tenancy()->end();
    }
});
