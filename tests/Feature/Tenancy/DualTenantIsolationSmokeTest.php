<?php

declare(strict_types=1);

use App\Enums\Content\ContentStatus;
use App\Enums\Landlord\TenantStatus;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Content\Page;
use App\Models\Tenant\HR\Department;
use App\Models\Tenant\HR\JobOpening;
use App\Models\Tenant\Seller;
use App\Models\Tenant\User;
use App\Services\Landlord\Tenant\TenantService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\HR\JobOpeningService;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

/**
 * Dual-tenant DB isolation smoke for CI.
 *
 * Provisions exactly two tenant databases once, then asserts Seller / HR / CONTENT
 * rows, domain resolution, and seller Sanctum tokens do not leak across tenants.
 */
beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);

    config([
        'tenancy.database.prefix' => 'testing_tenant_',
        'tenancy.database.suffix' => '',
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

/**
 * @return array{tenant: Tenant, domain: string}
 */
function provisionIsolatedTenant(string $label): array
{
    $suffix = Str::lower(Str::random(8));
    $domain = "iso-{$label}-{$suffix}.test";

    $tenant = app(TenantService::class)->store([
        'name' => "Isolation {$label}",
        'slug' => "iso-{$label}-{$suffix}",
        'email' => "owner-{$label}-{$suffix}@example.com",
        'status' => TenantStatus::Active->value,
        'is_active' => true,
        'domain' => $domain,
        'admin' => [
            'first_name' => 'Admin',
            'last_name' => $label,
            'email' => "admin-{$label}-{$suffix}@example.com",
            'password' => 'Password1!',
        ],
        'profile' => [
            'display_name' => "Isolation {$label}",
            'is_public' => true,
        ],
    ]);

    return [
        'tenant' => $tenant->fresh(['domains']) ?? $tenant,
        'domain' => $domain,
    ];
}

/**
 * @return array{seller_email: string, department_code: string, page_slug: string, user_email: string, token: string}
 */
function seedTenantIsolationFixtures(string $label): array
{
    app(CommerceSettingService::class)->setMarketplaceEnabled(true);
    app(CommerceSettingService::class)->set('seller.allow_registration', 'true');

    $sellerEmail = "seller-{$label}@example.com";
    $departmentCode = 'DEP-'.Str::upper($label);
    $pageSlug = "about-{$label}";
    $userEmail = "staff-{$label}@example.com";

    $seller = Seller::query()->create([
        'name' => "Seller {$label}",
        'email' => $sellerEmail,
        'password' => 'Password1!',
        'status' => SellerStatus::Active,
        'verification_status' => SellerVerificationStatus::Approved,
    ]);

    $department = Department::query()->create([
        'name' => "Department {$label}",
        'code' => $departmentCode,
        'is_active' => true,
    ]);

    $user = User::query()->create([
        'first_name' => 'Staff',
        'last_name' => $label,
        'email' => $userEmail,
        'password' => 'Password1!',
    ]);

    $user->employee()->create([
        'department_id' => $department->id,
        'job_title' => 'Associate',
        'employment_status' => EmploymentStatus::Active,
    ]);

    app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store([
        'title' => "Engineer {$label}",
        'slug' => 'backend-engineer',
        'status' => JobOpeningStatus::Draft,
    ]));

    JobOpening::query()->create([
        'title' => "Secret {$label}",
        'slug' => 'secret-role',
        'status' => JobOpeningStatus::Draft,
        'openings_count' => 1,
    ]);

    Page::query()->create([
        'title' => "About {$label}",
        'slug' => $pageSlug,
        'content' => "Private content for {$label}",
        'status' => ContentStatus::Published,
        'published_at' => now()->subMinute(),
    ]);

    return [
        'seller_email' => $sellerEmail,
        'department_code' => $departmentCode,
        'page_slug' => $pageSlug,
        'user_email' => $userEmail,
        'token' => $seller->createToken('api')->plainTextToken,
    ];
}

test('dual tenant isolation smoke covers seller hr content domain and tokens', function (): void {
    $tenantA = provisionIsolatedTenant('a');
    $tenantB = provisionIsolatedTenant('b');

    $fixturesA = $tenantA['tenant']->run(fn (): array => seedTenantIsolationFixtures('a'));
    $fixturesB = $tenantB['tenant']->run(fn (): array => seedTenantIsolationFixtures('b'));

    // DB isolation: Tenant A
    $tenantA['tenant']->run(function () use ($fixturesA, $fixturesB): void {
        expect(Seller::query()->count())->toBe(1)
            ->and(Seller::query()->where('email', $fixturesA['seller_email'])->exists())->toBeTrue()
            ->and(Seller::query()->where('email', $fixturesB['seller_email'])->exists())->toBeFalse()
            ->and(Department::query()->where('code', $fixturesA['department_code'])->exists())->toBeTrue()
            ->and(Department::query()->where('code', $fixturesB['department_code'])->exists())->toBeFalse()
            ->and(Page::query()->where('slug', $fixturesA['page_slug'])->exists())->toBeTrue()
            ->and(Page::query()->where('slug', $fixturesB['page_slug'])->exists())->toBeFalse()
            ->and(User::query()->where('email', $fixturesA['user_email'])->exists())->toBeTrue()
            ->and(User::query()->where('email', $fixturesB['user_email'])->exists())->toBeFalse();
    });

    // DB isolation: Tenant B
    $tenantB['tenant']->run(function () use ($fixturesA, $fixturesB): void {
        expect(Seller::query()->count())->toBe(1)
            ->and(Seller::query()->where('email', $fixturesB['seller_email'])->exists())->toBeTrue()
            ->and(Seller::query()->where('email', $fixturesA['seller_email'])->exists())->toBeFalse()
            ->and(Department::query()->where('code', $fixturesB['department_code'])->exists())->toBeTrue()
            ->and(Department::query()->where('code', $fixturesA['department_code'])->exists())->toBeFalse()
            ->and(Page::query()->where('slug', $fixturesB['page_slug'])->exists())->toBeTrue()
            ->and(Page::query()->where('slug', $fixturesA['page_slug'])->exists())->toBeFalse()
            ->and(User::query()->where('email', $fixturesB['user_email'])->exists())->toBeTrue()
            ->and(User::query()->where('email', $fixturesA['user_email'])->exists())->toBeFalse();
    });

    // Domain resolution
    $this->get('http://'.$tenantA['domain'].'/')->assertOk();
    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant('id'))->toBe($tenantA['tenant']->getTenantKey());
    tenancy()->end();

    $this->get('http://'.$tenantB['domain'].'/')->assertOk();
    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant('id'))->toBe($tenantB['tenant']->getTenantKey());
    tenancy()->end();

    // Seller token minted in A must not exist in Tenant B's token table,
    // and must remain resolvable in Tenant A (compare by hash, not id —
    // autoincrement ids collide across separate tenant databases).
    [, $plainTextToken] = explode('|', $fixturesA['token'], 2);
    $tokenHash = hash('sha256', $plainTextToken);

    $tenantB['tenant']->run(function () use ($tokenHash): void {
        expect(PersonalAccessToken::query()->where('token', $tokenHash)->exists())->toBeFalse();
    });

    $tenantA['tenant']->run(function () use ($tokenHash, $fixturesA): void {
        $token = PersonalAccessToken::query()->where('token', $tokenHash)->first();

        expect($token)->not->toBeNull()
            ->and($token?->tokenable)->toBeInstanceOf(Seller::class)
            ->and($token?->tokenable?->email)->toBe($fixturesA['seller_email']);
    });

    // HTTP: Tenant A token is unauthorized on Tenant B's seller API.
    $this->withToken($fixturesA['token'])
        ->getJson('http://'.$tenantB['domain'].'/api/seller/me')
        ->assertUnauthorized();

    $jobsA = $this->getJson('http://'.$tenantA['domain'].'/api/public/jobs')->assertSuccessful()->json('data');
    $jobsB = $this->getJson('http://'.$tenantB['domain'].'/api/public/jobs')->assertSuccessful()->json('data');

    expect(collect($jobsA)->pluck('title')->all())->toContain('Engineer a')
        ->and(collect($jobsA)->pluck('title')->all())->not->toContain('Secret a')
        ->and(collect($jobsA)->pluck('title')->all())->not->toContain('Engineer b')
        ->and(collect($jobsB)->pluck('title')->all())->toContain('Engineer b')
        ->and(collect($jobsB)->pluck('slug')->all())->toContain('backend-engineer');

    $this->getJson('http://'.$tenantA['domain'].'/api/public/jobs/secret-role')->assertNotFound();
    $this->getJson('http://'.$tenantA['domain'].'/api/candidates')->assertUnauthorized();

    $apply = $this->postJson('http://'.$tenantA['domain'].'/api/public/jobs/backend-engineer/applications', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'tenant_id' => $tenantB['tenant']->getTenantKey(),
        'status' => 'hired',
        'notes' => 'should be ignored',
    ])->assertSuccessful()->json();

    expect($apply['data'])->toBe(['received' => true])
        ->and(json_encode($apply))->not->toContain('ada@example.com');
});
