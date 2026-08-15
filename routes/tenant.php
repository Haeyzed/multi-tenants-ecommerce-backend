<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Auth\AuthController;
use App\Http\Controllers\Tenant\Brand\BrandController;
use App\Http\Controllers\Tenant\Category\CategoryController;
use App\Http\Controllers\Tenant\Inventory\InventoryController;
use App\Http\Controllers\Tenant\Media\MediaController;
use App\Http\Controllers\Tenant\Notification\DeviceController as NotificationDeviceController;
use App\Http\Controllers\Tenant\Notification\InboxController as NotificationInboxController;
use App\Http\Controllers\Tenant\Notification\PreferenceController as NotificationPreferenceController;
use App\Http\Controllers\Tenant\Product\ProductController;
use App\Http\Controllers\Tenant\Product\ProductVariantController;
use App\Http\Controllers\Tenant\RBAC\PermissionController;
use App\Http\Controllers\Tenant\RBAC\RoleController;
use App\Http\Controllers\Tenant\Subscription\SubscriptionController;
use App\Http\Controllers\Tenant\Unit\UnitController;
use App\Http\Controllers\Tenant\User\UserController;
use App\Http\Controllers\Tenant\Warehouse\WarehouseController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Routes for tenant applications. These are loaded by TenancyServiceProvider
| with the "tenant" middleware and initialize tenancy by domain.
|
*/

Route::middleware([
    Middleware\InitializeTenancyByDomain::class,
    Middleware\PreventAccessFromUnwantedDomains::class,
])->group(function (): void {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id')."\n";
    })->name('tenant.home');

    Route::prefix('api')->middleware(['api', 'tenant.guard'])->group(function (): void {
        Route::prefix('auth')->name('tenant.auth.')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

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
            Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('tenant.users.index');
            Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('tenant.users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.show')->whereNumber('user')->name('tenant.users.show');
            Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->whereNumber('user')->name('tenant.users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->whereNumber('user')->name('tenant.users.destroy');
            Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->middleware('permission:users.update')->whereNumber('user')->name('tenant.users.roles');
            Route::put('users/{user}/permissions', [UserController::class, 'syncPermissions'])->middleware('permission:users.update')->whereNumber('user')->name('tenant.users.permissions');

            Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('tenant.roles.index');
            Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('tenant.roles.store');
            Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.show')->whereNumber('role')->name('tenant.roles.show');
            Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->whereNumber('role')->name('tenant.roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->whereNumber('role')->name('tenant.roles.destroy');
            Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.update')->whereNumber('role')->name('tenant.roles.permissions');

            Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('tenant.permissions.index');
            Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create')->name('tenant.permissions.store');
            Route::get('permissions/{permission}', [PermissionController::class, 'show'])->middleware('permission:permissions.show')->whereNumber('permission')->name('tenant.permissions.show');
            Route::match(['put', 'patch'], 'permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.update')->whereNumber('permission')->name('tenant.permissions.update');
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete')->whereNumber('permission')->name('tenant.permissions.destroy');

            Route::get('media/options', [MediaController::class, 'options'])->name('tenant.media.options');
            Route::get('media', [MediaController::class, 'index'])->name('tenant.media.index');
            Route::post('media', [MediaController::class, 'store'])->name('tenant.media.store');
            Route::get('media/{media}', [MediaController::class, 'show'])->whereNumber('media')->name('tenant.media.show');
            Route::match(['put', 'patch'], 'media/{media}', [MediaController::class, 'update'])->whereNumber('media')->name('tenant.media.update');
            Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('tenant.media.destroy');

            Route::get('brands/options', [BrandController::class, 'options'])->middleware('permission:brands.view')->name('tenant.brands.options');
            Route::get('brands', [BrandController::class, 'index'])->middleware('permission:brands.view')->name('tenant.brands.index');
            Route::post('brands', [BrandController::class, 'store'])->middleware('permission:brands.create')->name('tenant.brands.store');
            Route::get('brands/{brand}', [BrandController::class, 'show'])->middleware('permission:brands.show')->whereNumber('brand')->name('tenant.brands.show');
            Route::match(['put', 'patch'], 'brands/{brand}', [BrandController::class, 'update'])->middleware('permission:brands.update')->whereNumber('brand')->name('tenant.brands.update');
            Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:brands.delete')->whereNumber('brand')->name('tenant.brands.destroy');
            Route::post('brands/{brand}/logo', [BrandController::class, 'storeLogo'])->middleware('permission:brands.update')->whereNumber('brand')->name('tenant.brands.logo.store');
            Route::delete('brands/{brand}/logo', [BrandController::class, 'destroyLogo'])->middleware('permission:brands.update')->whereNumber('brand')->name('tenant.brands.logo.destroy');

            Route::get('categories/options', [CategoryController::class, 'options'])->middleware('permission:categories.view')->name('tenant.categories.options');
            Route::get('categories/tree', [CategoryController::class, 'tree'])->middleware('permission:categories.view')->name('tenant.categories.tree');
            Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('tenant.categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:categories.create')->name('tenant.categories.store');
            Route::get('categories/{category}/children', [CategoryController::class, 'children'])->middleware('permission:categories.view')->whereNumber('category')->name('tenant.categories.children');
            Route::get('categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.show')->whereNumber('category')->name('tenant.categories.show');
            Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update')->whereNumber('category')->name('tenant.categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete')->whereNumber('category')->name('tenant.categories.destroy');
            Route::post('categories/{category}/image', [CategoryController::class, 'storeImage'])->middleware('permission:categories.update')->whereNumber('category')->name('tenant.categories.image.store');
            Route::delete('categories/{category}/image', [CategoryController::class, 'destroyImage'])->middleware('permission:categories.update')->whereNumber('category')->name('tenant.categories.image.destroy');

            Route::get('units/options', [UnitController::class, 'options'])->middleware('permission:units.view')->name('tenant.units.options');
            Route::get('units', [UnitController::class, 'index'])->middleware('permission:units.view')->name('tenant.units.index');
            Route::post('units', [UnitController::class, 'store'])->middleware('permission:units.create')->name('tenant.units.store');
            Route::get('units/{unit}', [UnitController::class, 'show'])->middleware('permission:units.show')->whereNumber('unit')->name('tenant.units.show');
            Route::match(['put', 'patch'], 'units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.update')->whereNumber('unit')->name('tenant.units.update');
            Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.delete')->whereNumber('unit')->name('tenant.units.destroy');

            Route::get('warehouses/options', [WarehouseController::class, 'options'])->middleware('permission:warehouses.view')->name('tenant.warehouses.options');
            Route::get('warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view')->name('tenant.warehouses.index');
            Route::post('warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create')->name('tenant.warehouses.store');
            Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.show')->whereNumber('warehouse')->name('tenant.warehouses.show');
            Route::match(['put', 'patch'], 'warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.update')->whereNumber('warehouse')->name('tenant.warehouses.update');
            Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete')->whereNumber('warehouse')->name('tenant.warehouses.destroy');
            Route::get('warehouses/{warehouse}/locations', [WarehouseController::class, 'indexLocations'])->middleware('permission:warehouses.view')->whereNumber('warehouse')->name('tenant.warehouses.locations.index');
            Route::post('warehouses/{warehouse}/locations', [WarehouseController::class, 'storeLocation'])->middleware('permission:warehouses.update')->whereNumber('warehouse')->name('tenant.warehouses.locations.store');
            Route::match(['put', 'patch'], 'warehouses/{warehouse}/locations/{location}', [WarehouseController::class, 'updateLocation'])->middleware('permission:warehouses.update')->whereNumber(['warehouse', 'location'])->name('tenant.warehouses.locations.update');
            Route::delete('warehouses/{warehouse}/locations/{location}', [WarehouseController::class, 'destroyLocation'])->middleware('permission:warehouses.update')->whereNumber(['warehouse', 'location'])->name('tenant.warehouses.locations.destroy');

            Route::get('products/options', [ProductController::class, 'options'])->middleware('permission:products.view')->name('tenant.products.options');
            Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('tenant.products.index');
            Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('tenant.products.store');
            Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.show')->whereNumber('product')->name('tenant.products.show');
            Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->whereNumber('product')->name('tenant.products.destroy');
            Route::post('products/{product}/images', [ProductController::class, 'storeImages'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.images.store');
            Route::delete('products/{product}/images', [ProductController::class, 'destroyImages'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.images.destroy');

            Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])->middleware('permission:variants.view')->whereNumber('product')->name('tenant.products.variants.index');
            Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->middleware('permission:variants.create')->whereNumber('product')->name('tenant.products.variants.store');
            Route::get('products/{product}/variants/{variant}', [ProductVariantController::class, 'show'])->middleware('permission:variants.show')->whereNumber(['product', 'variant'])->name('tenant.products.variants.show');
            Route::match(['put', 'patch'], 'products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.update');
            Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->middleware('permission:variants.delete')->whereNumber(['product', 'variant'])->name('tenant.products.variants.destroy');
            Route::post('products/{product}/variants/{variant}/image', [ProductVariantController::class, 'storeImage'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.image.store');
            Route::delete('products/{product}/variants/{variant}/image', [ProductVariantController::class, 'destroyImage'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.image.destroy');

            Route::get('inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('tenant.inventory.index');
            Route::get('inventory/{inventory}', [InventoryController::class, 'show'])->middleware('permission:inventory.view')->whereNumber('inventory')->name('tenant.inventory.show');
            Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.adjust');
            Route::post('inventory/{inventory}/reserve', [InventoryController::class, 'reserve'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.reserve');
            Route::post('inventory/{inventory}/release', [InventoryController::class, 'release'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.release');
            Route::post('inventory/{inventory}/transfer', [InventoryController::class, 'transfer'])->middleware('permission:inventory.transfer')->whereNumber('inventory')->name('tenant.inventory.transfer');

            Route::get('notifications/unread-count', [NotificationInboxController::class, 'unreadCount'])->name('tenant.notifications.unread-count');
            Route::get('notifications/unread', [NotificationInboxController::class, 'unread'])->name('tenant.notifications.unread');
            Route::post('notifications/read-all', [NotificationInboxController::class, 'markAllRead'])->name('tenant.notifications.read-all');
            Route::get('notifications', [NotificationInboxController::class, 'index'])->name('tenant.notifications.index');
            Route::get('notifications/{notification}', [NotificationInboxController::class, 'show'])->name('tenant.notifications.show');
            Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markRead'])->name('tenant.notifications.read');
            Route::post('notifications/{notification}/unread', [NotificationInboxController::class, 'markUnread'])->name('tenant.notifications.unread-one');
            Route::delete('notifications/{notification}', [NotificationInboxController::class, 'destroy'])->name('tenant.notifications.destroy');

            Route::get('notification-preferences', [NotificationPreferenceController::class, 'index'])->name('tenant.notification-preferences.index');
            Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])->name('tenant.notification-preferences.update');

            Route::get('devices', [NotificationDeviceController::class, 'index'])->name('tenant.devices.index');
            Route::post('devices', [NotificationDeviceController::class, 'store'])->name('tenant.devices.store');
            Route::delete('devices/{device}', [NotificationDeviceController::class, 'destroy'])->whereNumber('device')->name('tenant.devices.destroy');

            Route::prefix('subscription')->name('tenant.subscription.')->group(function (): void {
                Route::get('/', [SubscriptionController::class, 'current'])->name('current');
                Route::post('subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
                Route::post('verify', [SubscriptionController::class, 'verify'])->name('verify');
                Route::post('cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
                Route::post('change-plan', [SubscriptionController::class, 'changePlan'])->name('change-plan');
            });
        });
    });
});
