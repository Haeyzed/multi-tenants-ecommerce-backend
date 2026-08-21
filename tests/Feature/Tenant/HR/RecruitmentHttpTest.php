<?php

declare(strict_types=1);

use App\Enums\Landlord\TenantStatus;
use App\Enums\Tenant\HR\JobOfferStatus;
use App\Events\JobApplicationReceived;
use App\Events\JobApplicationStageChanged;
use App\Events\JobOfferAccepted;
use App\Events\JobOfferSent;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Services\Landlord\Tenant\TenantService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\JobApplicationService;
use App\Services\Tenant\HR\JobOfferService;
use App\Services\Tenant\HR\JobOpeningService;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);

    config([
        'tenancy.database.prefix' => 'testing_tenant_ats_',
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

    foreach (File::glob(database_path('testing_tenant_ats_*')) ?: [] as $database) {
        @File::delete($database);
    }
});

/**
 * @return array{tenant: Tenant, domain: string}
 */
function provisionAtsTenant(): array
{
    $suffix = Str::lower(Str::random(8));
    $domain = "ats-{$suffix}.test";

    $tenant = app(TenantService::class)->store([
        'name' => 'ATS Tenant',
        'slug' => "ats-{$suffix}",
        'email' => "owner-{$suffix}@example.com",
        'status' => TenantStatus::Active->value,
        'is_active' => true,
        'domain' => $domain,
        'admin' => [
            'first_name' => 'Admin',
            'last_name' => 'ATS',
            'email' => "admin-{$suffix}@example.com",
            'password' => 'Password1!',
        ],
        'profile' => [
            'display_name' => 'ATS Tenant',
            'is_public' => true,
        ],
    ]);

    return [
        'tenant' => $tenant->fresh(['domains']) ?? $tenant,
        'domain' => $domain,
    ];
}

test('public career endpoints hide drafts and let candidates accept offers by token', function (): void {
    $provisioned = provisionAtsTenant();
    $domain = $provisioned['domain'];

    $token = $provisioned['tenant']->run(function (): string {
        Event::fake([
            JobApplicationReceived::class,
            JobApplicationStageChanged::class,
            JobOfferSent::class,
            JobOfferAccepted::class,
        ]);

        $openings = app(JobOpeningService::class);
        $published = $openings->publish($openings->store(['title' => 'Published Role', 'slug' => 'published-role']));
        $openings->store(['title' => 'Draft Role', 'slug' => 'draft-role']);

        $admin = User::query()->firstOrFail();
        $application = app(JobApplicationService::class)->store([
            'job_opening_id' => $published->id,
            'first_name' => 'Chioma',
            'last_name' => 'Eze',
            'email' => 'chioma@example.com',
        ], $admin);

        $offer = app(JobOfferService::class)->store([
            'job_application_id' => $application->id,
            'salary' => '250000.00',
            'currency' => 'NGN',
        ]);

        app(JobOfferService::class)->send($offer, $admin);

        $plain = null;
        Event::assertDispatched(JobOfferSent::class, function (JobOfferSent $event) use (&$plain): bool {
            $plain = $event->publicToken;

            return is_string($event->publicToken) && $event->publicToken !== '';
        });

        return (string) $plain;
    });

    $this->getJson('http://'.$domain.'/api/public/jobs')
        ->assertSuccessful()
        ->assertJsonMissing(['title' => 'Draft Role'])
        ->assertJsonFragment(['title' => 'Published Role'])
        ->assertJsonMissing(['email' => 'chioma@example.com']);

    $this->getJson('http://'.$domain.'/api/public/jobs/draft-role')->assertNotFound();

    $this->getJson('http://'.$domain.'/api/public/offers/'.$token)
        ->assertSuccessful()
        ->assertJsonPath('data.position', 'Published Role')
        ->assertJsonMissing(['notes']);

    $this->postJson('http://'.$domain.'/api/public/offers/'.$token.'/accept')
        ->assertSuccessful()
        ->assertJsonPath('data.received', true)
        ->assertJsonPath('data.status', JobOfferStatus::Accepted->value);

    $this->postJson('http://'.$domain.'/api/public/offers/'.$token.'/accept')
        ->assertUnprocessable();
});

test('public career endpoints return forbidden when recruitment is disabled', function (): void {
    $provisioned = provisionAtsTenant();
    $domain = $provisioned['domain'];

    $provisioned['tenant']->run(function (): void {
        app(HrSettingsService::class)->update([
            'hr.recruitment.enabled' => false,
        ]);
    });

    $this->getJson('http://'.$domain.'/api/public/jobs')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});
