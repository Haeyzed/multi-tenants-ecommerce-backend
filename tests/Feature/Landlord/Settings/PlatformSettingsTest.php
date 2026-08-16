<?php

declare(strict_types=1);

use App\Models\Landlord\User;
use App\Services\Landlord\Settings\PlatformSettingsService;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);
});

test('platform settings domain returns defaults and updates allowlisted keys', function (): void {
    $settings = app(PlatformSettingsService::class);

    $platform = $settings->getDomain('platform');

    expect($platform['platform.maintenance_mode'])->toBeFalse()
        ->and($platform['platform.name'])->toBe('Multi-tenant Ecommerce');

    $updated = $settings->updateDomain('platform', [
        'platform.name' => 'Acme Commerce Cloud',
        'platform.support_email' => 'support@acme.test',
        'platform.maintenance_mode' => true,
        'platform.maintenance_message' => 'Upgrading',
    ]);

    expect($updated['platform.name'])->toBe('Acme Commerce Cloud')
        ->and($updated['platform.support_email'])->toBe('support@acme.test')
        ->and($updated['platform.maintenance_mode'])->toBeTrue()
        ->and($settings->get('platform.name'))->toBe('Acme Commerce Cloud');
});

test('registration and localization domains persist', function (): void {
    $settings = app(PlatformSettingsService::class);

    $registration = $settings->updateDomain('registration', [
        'registration.tenant_registration_enabled' => false,
        'registration.default_plan_slug' => 'starter',
    ]);

    $localization = $settings->updateDomain('localization', [
        'localization.default_currency' => 'USD',
        'localization.default_timezone' => 'Africa/Lagos',
    ]);

    expect($registration['registration.tenant_registration_enabled'])->toBeFalse()
        ->and($registration['registration.default_plan_slug'])->toBe('starter')
        ->and($localization['localization.default_currency'])->toBe('USD')
        ->and($localization['localization.default_timezone'])->toBe('Africa/Lagos');
});

test('unknown platform setting keys are rejected', function (): void {
    $settings = app(PlatformSettingsService::class);

    expect(fn () => $settings->updateDomain('platform', [
        'platform.secret_api_key' => 'nope',
    ]))->toThrow(ValidationException::class);
});

test('landlord settings api show and update by domain', function (): void {
    $user = User::factory()->create();
    $user->syncRoles(['admin']);
    Sanctum::actingAs($user, ['*'], 'landlord');

    $show = $this->getJson('/api/settings/platform')->assertOk();

    expect($show->json('success'))->toBeTrue()
        ->and($show->json('data.domain'))->toBe('platform')
        ->and($show->json('data.settings')['platform.maintenance_mode'])->toBeFalse();

    $update = $this->putJson('/api/settings/platform', [
        'platform.name' => 'Platform Via API',
        'platform.support_phone' => '+2348000000000',
    ])->assertOk();

    expect($update->json('data.settings')['platform.name'])->toBe('Platform Via API')
        ->and($update->json('data.settings')['platform.support_phone'])->toBe('+2348000000000');

    $localization = $this->getJson('/api/settings/localization')->assertOk();

    expect($localization->json('data.settings')['localization.default_currency'])->toBe('NGN');
});
