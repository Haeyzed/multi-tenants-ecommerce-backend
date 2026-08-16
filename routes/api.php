<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\Auth\AuthController;
use App\Http\Controllers\Landlord\Cms\BlogCategoryController as LandlordBlogCategoryController;
use App\Http\Controllers\Landlord\Cms\BlogPostController as LandlordBlogPostController;
use App\Http\Controllers\Landlord\Cms\PageController as LandlordPageController;
use App\Http\Controllers\Landlord\Domain\DomainController;
use App\Http\Controllers\Landlord\Feature\FeatureController;
use App\Http\Controllers\Landlord\Media\MediaController;
use App\Http\Controllers\Landlord\Notification\DeviceController as NotificationDeviceController;
use App\Http\Controllers\Landlord\Notification\InboxController as NotificationInboxController;
use App\Http\Controllers\Landlord\Notification\NotificationTemplateController;
use App\Http\Controllers\Landlord\Notification\PreferenceController as NotificationPreferenceController;
use App\Http\Controllers\Landlord\Plan\PlanController;
use App\Http\Controllers\Landlord\RBAC\PermissionController;
use App\Http\Controllers\Landlord\RBAC\RoleController;
use App\Http\Controllers\Landlord\Settings\PlatformSettingsController;
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
use App\Http\Controllers\Public\Cms\PublicCmsController;
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
            Route::middleware('throttle:6,1')->group(function (): void {
                Route::post('login', [AuthController::class, 'login'])->name('login');
                Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
                Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
            });

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('logout', [AuthController::class, 'logout'])->name('logout');
                Route::get('me', [AuthController::class, 'me'])->name('me');
                Route::match(['put', 'patch'], 'profile', [AuthController::class, 'updateProfile'])->name('profile');
                Route::post('avatar', [AuthController::class, 'storeAvatar'])->name('avatar.store');
                Route::delete('avatar', [AuthController::class, 'destroyAvatar'])->name('avatar.destroy');
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
            Route::post('tenants/{tenant}/profile/logo', [TenantProfileController::class, 'storeLogo'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.logo.store');
            Route::delete('tenants/{tenant}/profile/logo', [TenantProfileController::class, 'destroyLogo'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.logo.destroy');
            Route::post('tenants/{tenant}/profile/cover', [TenantProfileController::class, 'storeCover'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.cover.store');
            Route::delete('tenants/{tenant}/profile/cover', [TenantProfileController::class, 'destroyCover'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.cover.destroy');

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

            Route::get('settings/{domain}', [PlatformSettingsController::class, 'show'])->middleware('permission:settings.view')->name('landlord.settings.show');
            Route::match(['put', 'patch'], 'settings/{domain}', [PlatformSettingsController::class, 'update'])->middleware('permission:settings.update')->name('landlord.settings.update');

            Route::get('blog-categories/options', [LandlordBlogCategoryController::class, 'options'])->middleware('permission:cms.view|cms.manage')->name('landlord.blog-categories.options');
            Route::get('blog-categories', [LandlordBlogCategoryController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('landlord.blog-categories.index');
            Route::post('blog-categories', [LandlordBlogCategoryController::class, 'store'])->middleware('permission:cms.manage')->name('landlord.blog-categories.store');
            Route::get('blog-categories/{blog_category}', [LandlordBlogCategoryController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('blog_category')->name('landlord.blog-categories.show');
            Route::match(['put', 'patch'], 'blog-categories/{blog_category}', [LandlordBlogCategoryController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('blog_category')->name('landlord.blog-categories.update');
            Route::delete('blog-categories/{blog_category}', [LandlordBlogCategoryController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('blog_category')->name('landlord.blog-categories.destroy');

            Route::get('blog-posts', [LandlordBlogPostController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('landlord.blog-posts.index');
            Route::post('blog-posts', [LandlordBlogPostController::class, 'store'])->middleware('permission:cms.manage')->name('landlord.blog-posts.store');
            Route::get('blog-posts/{blog_post}', [LandlordBlogPostController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.show');
            Route::match(['put', 'patch'], 'blog-posts/{blog_post}', [LandlordBlogPostController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.update');
            Route::delete('blog-posts/{blog_post}', [LandlordBlogPostController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.destroy');
            Route::post('blog-posts/{blog_post}/featured-image', [LandlordBlogPostController::class, 'storeFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.featured-image.store');
            Route::delete('blog-posts/{blog_post}/featured-image', [LandlordBlogPostController::class, 'destroyFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.featured-image.destroy');

            Route::get('pages', [LandlordPageController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('landlord.pages.index');
            Route::post('pages', [LandlordPageController::class, 'store'])->middleware('permission:cms.manage')->name('landlord.pages.store');
            Route::get('pages/{page}', [LandlordPageController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('page')->name('landlord.pages.show');
            Route::match(['put', 'patch'], 'pages/{page}', [LandlordPageController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('page')->name('landlord.pages.update');
            Route::delete('pages/{page}', [LandlordPageController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('page')->name('landlord.pages.destroy');
            Route::post('pages/{page}/featured-image', [LandlordPageController::class, 'storeFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('page')->name('landlord.pages.featured-image.store');
            Route::delete('pages/{page}/featured-image', [LandlordPageController::class, 'destroyFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('page')->name('landlord.pages.featured-image.destroy');

            Route::get('media/options', [MediaController::class, 'options'])->name('landlord.media.options');
            Route::get('media', [MediaController::class, 'index'])->name('landlord.media.index');
            Route::post('media', [MediaController::class, 'store'])->name('landlord.media.store');
            Route::get('media/{media}', [MediaController::class, 'show'])->whereNumber('media')->name('landlord.media.show');
            Route::match(['put', 'patch'], 'media/{media}', [MediaController::class, 'update'])->whereNumber('media')->name('landlord.media.update');
            Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('landlord.media.destroy');

            Route::get('notifications/unread-count', [NotificationInboxController::class, 'unreadCount'])->name('landlord.notifications.unread-count');
            Route::get('notifications/unread', [NotificationInboxController::class, 'unread'])->name('landlord.notifications.unread');
            Route::post('notifications/read-all', [NotificationInboxController::class, 'markAllRead'])->name('landlord.notifications.read-all');
            Route::get('notifications', [NotificationInboxController::class, 'index'])->name('landlord.notifications.index');
            Route::get('notifications/{notification}', [NotificationInboxController::class, 'show'])->name('landlord.notifications.show');
            Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markRead'])->name('landlord.notifications.read');
            Route::post('notifications/{notification}/unread', [NotificationInboxController::class, 'markUnread'])->name('landlord.notifications.unread-one');
            Route::delete('notifications/{notification}', [NotificationInboxController::class, 'destroy'])->name('landlord.notifications.destroy');

            Route::get('notification-preferences', [NotificationPreferenceController::class, 'index'])->name('landlord.notification-preferences.index');
            Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])->name('landlord.notification-preferences.update');

            Route::get('devices', [NotificationDeviceController::class, 'index'])->name('landlord.devices.index');
            Route::post('devices', [NotificationDeviceController::class, 'store'])->name('landlord.devices.store');
            Route::delete('devices/{device}', [NotificationDeviceController::class, 'destroy'])->whereNumber('device')->name('landlord.devices.destroy');

            Route::get('notification-templates/options', [NotificationTemplateController::class, 'options'])->middleware('permission:notification-templates.view')->name('landlord.notification-templates.options');
            Route::get('notification-templates', [NotificationTemplateController::class, 'index'])->middleware('permission:notification-templates.view')->name('landlord.notification-templates.index');
            Route::post('notification-templates', [NotificationTemplateController::class, 'store'])->middleware('permission:notification-templates.create')->name('landlord.notification-templates.store');
            Route::get('notification-templates/{notification_template}', [NotificationTemplateController::class, 'show'])->middleware('permission:notification-templates.show')->whereNumber('notification_template')->name('landlord.notification-templates.show');
            Route::match(['put', 'patch'], 'notification-templates/{notification_template}', [NotificationTemplateController::class, 'update'])->middleware('permission:notification-templates.update')->whereNumber('notification_template')->name('landlord.notification-templates.update');
            Route::delete('notification-templates/{notification_template}', [NotificationTemplateController::class, 'destroy'])->middleware('permission:notification-templates.delete')->whereNumber('notification_template')->name('landlord.notification-templates.destroy');
            Route::post('notification-templates/{notification_template}/preview', [NotificationTemplateController::class, 'preview'])->middleware('permission:notification-templates.view')->whereNumber('notification_template')->name('landlord.notification-templates.preview');
        });
    });

    Route::prefix('public')->middleware('central')->name('public.')->group(function (): void {
        Route::get('plans', [PublicPlanController::class, 'index'])->name('plans.index');
        Route::get('stores/{slug}', [PublicTenantProfileController::class, 'show'])->name('stores.show');
        Route::get('pages/{slug}', [PublicCmsController::class, 'showPage'])->name('pages.show');
        Route::get('blog/posts', [PublicCmsController::class, 'indexPosts'])->name('blog.posts.index');
    });

    Route::post('webhooks/{provider}', WebhookController::class)
        ->middleware(['central', 'throttle:120,1'])
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

foreach (config('tenancy.identification.central_domains', []) as $index => $domain) {
    // Named routes must be unique for `route:cache`. Keep canonical names on the
    // primary central domain; prefix secondary domains so the same URI tree can
    // exist on localhost / 127.0.0.1 / APP_URL host without colliding.
    if ($index === 0) {
        Route::domain($domain)->group($registerLandlordApi);

        continue;
    }

    Route::domain($domain)->name("central{$index}.")->group($registerLandlordApi);
}
