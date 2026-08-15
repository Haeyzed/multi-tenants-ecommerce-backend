<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\Auth\AuthController;
use App\Http\Controllers\Landlord\Domain\DomainController;
use App\Http\Controllers\Landlord\Feature\FeatureController;
use App\Http\Controllers\Landlord\Plan\PlanController;
use App\Http\Controllers\Landlord\RBAC\PermissionController;
use App\Http\Controllers\Landlord\RBAC\RoleController;
use App\Http\Controllers\Landlord\Subscription\SubscriptionController as LandlordSubscriptionController;
use App\Http\Controllers\Landlord\Tenant\TenantController;
use App\Http\Controllers\Landlord\TenantProfile\TenantProfileController;
use App\Http\Controllers\Landlord\User\UserController;
use App\Http\Controllers\Landlord\World\CityController;
use App\Http\Controllers\Landlord\World\CountryController;
use App\Http\Controllers\Landlord\World\CurrencyController;
use App\Http\Controllers\Landlord\World\GeolocateController;
use App\Http\Controllers\Landlord\World\LanguageController;
use App\Http\Controllers\Landlord\World\StateController;
use App\Http\Controllers\Landlord\World\TimezoneController;
use App\Http\Controllers\Public\Plan\PlanController as PublicPlanController;
use App\Http\Controllers\Public\TenantProfile\TenantProfileController as PublicTenantProfileController;
use App\Http\Controllers\Webhook\WebhookController;
use App\Http\Middleware\SetLandlordGuard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landlord (Central) API Routes
|--------------------------------------------------------------------------
|
| API routes for the central / landlord application. Prefixed with /api
| and loaded with the "api" middleware group.
|
| Auth/Users/RBAC are bound to central domains so tenant routes with the
| same URI paths cannot overwrite them.
|
*/

$registerLandlordApi = function (): void {
    Route::middleware([SetLandlordGuard::class, 'central'])->group(function (): void {
        Route::prefix('auth')->name('landlord.auth.')->group(function (): void {
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('logout', [AuthController::class, 'logout'])->name('logout');
                Route::get('me', [AuthController::class, 'me'])->name('me');
                Route::match(['put', 'patch'], 'profile', [AuthController::class, 'updateProfile'])->name('profile');
                Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
            });
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('landlord.users.index');
            Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('landlord.users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.show')->whereNumber('user')->name('landlord.users.show');
            Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->whereNumber('user')->name('landlord.users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->whereNumber('user')->name('landlord.users.destroy');
            Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->middleware('permission:users.update')->whereNumber('user')->name('landlord.users.roles');
            Route::put('users/{user}/permissions', [UserController::class, 'syncPermissions'])->middleware('permission:users.update')->whereNumber('user')->name('landlord.users.permissions');

            Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('landlord.roles.index');
            Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('landlord.roles.store');
            Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.show')->whereNumber('role')->name('landlord.roles.show');
            Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->whereNumber('role')->name('landlord.roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->whereNumber('role')->name('landlord.roles.destroy');
            Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.update')->whereNumber('role')->name('landlord.roles.permissions');

            Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('landlord.permissions.index');
            Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create')->name('landlord.permissions.store');
            Route::get('permissions/{permission}', [PermissionController::class, 'show'])->middleware('permission:permissions.show')->whereNumber('permission')->name('landlord.permissions.show');
            Route::match(['put', 'patch'], 'permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.update')->whereNumber('permission')->name('landlord.permissions.update');
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete')->whereNumber('permission')->name('landlord.permissions.destroy');

            Route::get('tenants/options', [TenantController::class, 'options'])->middleware('permission:tenants.view')->name('landlord.tenants.options');
            Route::get('tenants', [TenantController::class, 'index'])->middleware('permission:tenants.view')->name('landlord.tenants.index');
            Route::post('tenants', [TenantController::class, 'store'])->middleware('permission:tenants.create')->name('landlord.tenants.store');
            Route::get('tenants/{tenant}', [TenantController::class, 'show'])->middleware('permission:tenants.show')->name('landlord.tenants.show');
            Route::match(['put', 'patch'], 'tenants/{tenant}', [TenantController::class, 'update'])->middleware('permission:tenants.update')->name('landlord.tenants.update');
            Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->middleware('permission:tenants.delete')->name('landlord.tenants.destroy');

            Route::get('tenants/{tenant}/domains', [DomainController::class, 'index'])->middleware('permission:domains.view')->name('landlord.tenants.domains.index');
            Route::post('tenants/{tenant}/domains', [DomainController::class, 'store'])->middleware('permission:domains.create')->name('landlord.tenants.domains.store');
            Route::get('tenants/{tenant}/domains/{domain}', [DomainController::class, 'show'])->middleware('permission:domains.show')->whereNumber('domain')->name('landlord.tenants.domains.show');
            Route::match(['put', 'patch'], 'tenants/{tenant}/domains/{domain}', [DomainController::class, 'update'])->middleware('permission:domains.update')->whereNumber('domain')->name('landlord.tenants.domains.update');
            Route::delete('tenants/{tenant}/domains/{domain}', [DomainController::class, 'destroy'])->middleware('permission:domains.delete')->whereNumber('domain')->name('landlord.tenants.domains.destroy');
            Route::post('tenants/{tenant}/domains/{domain}/primary', [DomainController::class, 'makePrimary'])->middleware('permission:domains.update')->whereNumber('domain')->name('landlord.tenants.domains.primary');

            Route::get('tenants/{tenant}/profile', [TenantProfileController::class, 'show'])->middleware('permission:tenant-profiles.show')->name('landlord.tenants.profile.show');
            Route::post('tenants/{tenant}/profile', [TenantProfileController::class, 'store'])->middleware('permission:tenant-profiles.create')->name('landlord.tenants.profile.store');
            Route::match(['put', 'patch'], 'tenants/{tenant}/profile', [TenantProfileController::class, 'update'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.update');
            Route::delete('tenants/{tenant}/profile', [TenantProfileController::class, 'destroy'])->middleware('permission:tenant-profiles.delete')->name('landlord.tenants.profile.destroy');

            Route::get('tenants/{tenant}/subscription', [LandlordSubscriptionController::class, 'current'])->middleware('permission:subscriptions.show')->name('landlord.tenants.subscription.current');
            Route::post('tenants/{tenant}/subscription/subscribe', [LandlordSubscriptionController::class, 'subscribe'])->middleware('permission:subscriptions.create')->name('landlord.tenants.subscription.subscribe');
            Route::post('tenants/{tenant}/subscription/verify', [LandlordSubscriptionController::class, 'verify'])->middleware('permission:subscriptions.update')->name('landlord.tenants.subscription.verify');
            Route::post('tenants/{tenant}/subscription/{subscription}/cancel', [LandlordSubscriptionController::class, 'cancel'])->middleware('permission:subscriptions.update')->whereNumber('subscription')->name('landlord.tenants.subscription.cancel');
            Route::post('tenants/{tenant}/subscription/change-plan', [LandlordSubscriptionController::class, 'changePlan'])->middleware('permission:subscriptions.update')->name('landlord.tenants.subscription.change-plan');

            Route::get('features/options', [FeatureController::class, 'options'])->middleware('permission:features.view')->name('landlord.features.options');
            Route::get('features', [FeatureController::class, 'index'])->middleware('permission:features.view')->name('landlord.features.index');
            Route::post('features', [FeatureController::class, 'store'])->middleware('permission:features.create')->name('landlord.features.store');
            Route::get('features/{feature}', [FeatureController::class, 'show'])->middleware('permission:features.show')->whereNumber('feature')->name('landlord.features.show');
            Route::match(['put', 'patch'], 'features/{feature}', [FeatureController::class, 'update'])->middleware('permission:features.update')->whereNumber('feature')->name('landlord.features.update');
            Route::delete('features/{feature}', [FeatureController::class, 'destroy'])->middleware('permission:features.delete')->whereNumber('feature')->name('landlord.features.destroy');

            Route::get('plans/options', [PlanController::class, 'options'])->middleware('permission:plans.view')->name('landlord.plans.options');
            Route::get('plans', [PlanController::class, 'index'])->middleware('permission:plans.view')->name('landlord.plans.index');
            Route::post('plans', [PlanController::class, 'store'])->middleware('permission:plans.create')->name('landlord.plans.store');
            Route::get('plans/{plan}', [PlanController::class, 'show'])->middleware('permission:plans.show')->whereNumber('plan')->name('landlord.plans.show');
            Route::match(['put', 'patch'], 'plans/{plan}', [PlanController::class, 'update'])->middleware('permission:plans.update')->whereNumber('plan')->name('landlord.plans.update');
            Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:plans.delete')->whereNumber('plan')->name('landlord.plans.destroy');
            Route::put('plans/{plan}/features', [PlanController::class, 'syncFeatures'])->middleware('permission:plans.update')->whereNumber('plan')->name('landlord.plans.features');
        });
    });

    Route::prefix('public')->middleware('central')->name('public.')->group(function (): void {
        Route::get('plans', [PublicPlanController::class, 'index'])->name('plans.index');
        Route::get('stores/{slug}', [PublicTenantProfileController::class, 'show'])->name('stores.show');
    });

    Route::post('webhooks/{provider}', WebhookController::class)
        ->middleware('central')
        ->name('webhooks.provider');

    Route::prefix('world')->middleware('central')->name('landlord.world.')->group(function (): void {
        Route::get('geolocate/ip', [GeolocateController::class, 'ip'])->name('geolocate.ip');
        Route::get('geolocate', [GeolocateController::class, 'index'])->name('geolocate');

        Route::get('countries/options', [CountryController::class, 'options'])->name('countries.options');
        Route::get('countries', [CountryController::class, 'index'])->name('countries.index');
        Route::get('countries/{country}', [CountryController::class, 'show'])->whereNumber('country')->name('countries.show');

        Route::get('states/options', [StateController::class, 'options'])->name('states.options');
        Route::get('states', [StateController::class, 'index'])->name('states.index');
        Route::get('states/{state}', [StateController::class, 'show'])->whereNumber('state')->name('states.show');

        Route::get('cities/options', [CityController::class, 'options'])->name('cities.options');
        Route::get('cities', [CityController::class, 'index'])->name('cities.index');
        Route::get('cities/{city}', [CityController::class, 'show'])->whereNumber('city')->name('cities.show');

        Route::get('currencies/options', [CurrencyController::class, 'options'])->name('currencies.options');
        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::get('currencies/{currency}', [CurrencyController::class, 'show'])->whereNumber('currency')->name('currencies.show');

        Route::get('timezones/options', [TimezoneController::class, 'options'])->name('timezones.options');
        Route::get('timezones', [TimezoneController::class, 'index'])->name('timezones.index');
        Route::get('timezones/{timezone}', [TimezoneController::class, 'show'])->whereNumber('timezone')->name('timezones.show');

        Route::get('languages/options', [LanguageController::class, 'options'])->name('languages.options');
        Route::get('languages', [LanguageController::class, 'index'])->name('languages.index');
        Route::get('languages/{language}', [LanguageController::class, 'show'])->whereNumber('language')->name('languages.show');
    });
};

foreach (config('tenancy.identification.central_domains', []) as $domain) {
    Route::domain($domain)->group($registerLandlordApi);
}
