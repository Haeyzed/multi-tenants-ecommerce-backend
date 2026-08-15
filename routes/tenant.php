<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Accounting\AccountController;
use App\Http\Controllers\Tenant\Accounting\JournalEntryController;
use App\Http\Controllers\Tenant\Auth\AuthController;
use App\Http\Controllers\Tenant\Brand\BrandController;
use App\Http\Controllers\Tenant\Catalog\CollectionController;
use App\Http\Controllers\Tenant\Catalog\ProductAttributeController;
use App\Http\Controllers\Tenant\Catalog\ProductBadgeController;
use App\Http\Controllers\Tenant\Catalog\ProductOptionController;
use App\Http\Controllers\Tenant\Catalog\ProductTagController;
use App\Http\Controllers\Tenant\Catalog\SeoController;
use App\Http\Controllers\Tenant\Category\CategoryController;
use App\Http\Controllers\Tenant\Commerce\CartController;
use App\Http\Controllers\Tenant\Commerce\CheckoutController;
use App\Http\Controllers\Tenant\Commerce\CustomerOrderController;
use App\Http\Controllers\Tenant\Commerce\CustomerShipmentController;
use App\Http\Controllers\Tenant\Commerce\OrderController;
use App\Http\Controllers\Tenant\Commerce\PaymentController;
use App\Http\Controllers\Tenant\Commerce\PaymentWebhookController;
use App\Http\Controllers\Tenant\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Tenant\Customer\CustomerController;
use App\Http\Controllers\Tenant\Customer\ProductReviewController as CustomerProductReviewController;
use App\Http\Controllers\Tenant\Inventory\InventoryController;
use App\Http\Controllers\Tenant\Media\MediaController;
use App\Http\Controllers\Tenant\Notification\DeviceController as NotificationDeviceController;
use App\Http\Controllers\Tenant\Notification\InboxController as NotificationInboxController;
use App\Http\Controllers\Tenant\Notification\PreferenceController as NotificationPreferenceController;
use App\Http\Controllers\Tenant\Procurement\PurchaseOrderController;
use App\Http\Controllers\Tenant\Procurement\SupplierController;
use App\Http\Controllers\Tenant\Product\ProductBundleController;
use App\Http\Controllers\Tenant\Product\ProductController;
use App\Http\Controllers\Tenant\Product\ProductRelationController;
use App\Http\Controllers\Tenant\Product\ProductReviewController;
use App\Http\Controllers\Tenant\Product\ProductSpecificationController;
use App\Http\Controllers\Tenant\Product\ProductVariantController;
use App\Http\Controllers\Tenant\RBAC\PermissionController;
use App\Http\Controllers\Tenant\RBAC\RoleController;
use App\Http\Controllers\Tenant\Shipping\ShipmentController;
use App\Http\Controllers\Tenant\Shipping\ShippingMethodController;
use App\Http\Controllers\Tenant\Storefront\StorefrontBrandController;
use App\Http\Controllers\Tenant\Storefront\StorefrontCategoryController;
use App\Http\Controllers\Tenant\Storefront\StorefrontCollectionController;
use App\Http\Controllers\Tenant\Storefront\StorefrontProductController;
use App\Http\Controllers\Tenant\Storefront\StorefrontReviewController;
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

    Route::prefix('api')->middleware('api')->group(function (): void {
        Route::prefix('storefront')->name('storefront.')->group(function (): void {
            Route::get('products', [StorefrontProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [StorefrontProductController::class, 'show'])->name('products.show');
            Route::get('products/{product}/reviews', [StorefrontReviewController::class, 'index'])->whereNumber('product')->name('products.reviews.index');
            Route::get('collections', [StorefrontCollectionController::class, 'index'])->name('collections.index');
            Route::get('collections/{collection}', [StorefrontCollectionController::class, 'show'])->name('collections.show');
            Route::get('brands', [StorefrontBrandController::class, 'index'])->name('brands.index');
            Route::get('brands/{brand}', [StorefrontBrandController::class, 'show'])->name('brands.show');
            Route::get('categories', [StorefrontCategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/tree', [StorefrontCategoryController::class, 'tree'])->name('categories.tree');
            Route::get('categories/{category}', [StorefrontCategoryController::class, 'show'])->name('categories.show');
        });

        Route::prefix('customer')->middleware('customer.guard')->name('customer.')->group(function (): void {
            Route::middleware('throttle:6,1')->group(function (): void {
                Route::post('register', [CustomerAuthController::class, 'register'])->name('register');
                Route::post('login', [CustomerAuthController::class, 'login'])->name('login');
                Route::post('forgot-password', [CustomerAuthController::class, 'forgotPassword'])->name('forgot-password');
                Route::post('reset-password', [CustomerAuthController::class, 'resetPassword'])->name('reset-password');
            });

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');
                Route::get('me', [CustomerAuthController::class, 'me'])->name('me');
                Route::match(['put', 'patch'], 'profile', [CustomerAuthController::class, 'updateProfile'])->name('profile');
                Route::post('avatar', [CustomerAuthController::class, 'storeAvatar'])->name('avatar.store');
                Route::delete('avatar', [CustomerAuthController::class, 'destroyAvatar'])->name('avatar.destroy');
                Route::post('change-password', [CustomerAuthController::class, 'changePassword'])->name('change-password');
                Route::post('email/verify', [CustomerAuthController::class, 'verifyEmail'])->name('email.verify');
                Route::middleware('throttle:6,1')->post('email/resend', [CustomerAuthController::class, 'resendVerification'])->name('email.resend');
                Route::delete('account', [CustomerAuthController::class, 'destroyAccount'])->name('account.destroy');

                Route::get('addresses', [CustomerAuthController::class, 'indexAddresses'])->name('addresses.index');
                Route::post('addresses', [CustomerAuthController::class, 'storeAddress'])->name('addresses.store');
                Route::match(['put', 'patch'], 'addresses/{address}', [CustomerAuthController::class, 'updateAddress'])->whereNumber('address')->name('addresses.update');
                Route::delete('addresses/{address}', [CustomerAuthController::class, 'destroyAddress'])->whereNumber('address')->name('addresses.destroy');
                Route::post('addresses/{address}/default', [CustomerAuthController::class, 'makeDefaultAddress'])->whereNumber('address')->name('addresses.default');

                Route::post('products/{product}/reviews', [CustomerProductReviewController::class, 'store'])->whereNumber('product')->name('products.reviews.store');

                Route::get('orders', [CustomerOrderController::class, 'index'])->name('orders.index');
                Route::get('orders/{order}', [CustomerOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
                Route::get('orders/{order}/shipments', [CustomerShipmentController::class, 'index'])->whereNumber('order')->name('orders.shipments.index');
            });
        });

        Route::middleware(['customer.guard', 'auth:sanctum'])->group(function (): void {
            Route::get('cart', [CartController::class, 'show'])->name('customer.cart.show');
            Route::delete('cart', [CartController::class, 'destroy'])->name('customer.cart.destroy');
            Route::post('cart/items', [CartController::class, 'storeItem'])->name('customer.cart.items.store');
            Route::patch('cart/items/{item}', [CartController::class, 'updateItem'])->whereNumber('item')->name('customer.cart.items.update');
            Route::delete('cart/items/{item}', [CartController::class, 'destroyItem'])->whereNumber('item')->name('customer.cart.items.destroy');
            Route::post('checkout', [CheckoutController::class, 'store'])->name('customer.checkout.store');
            Route::post('checkout/pay', [PaymentController::class, 'pay'])->name('customer.checkout.pay');
            Route::post('payments/verify', [PaymentController::class, 'verify'])->name('customer.payments.verify');
        });

        Route::post('payments/webhooks/paystack', [PaymentWebhookController::class, 'paystack'])
            ->name('tenant.payments.webhooks.paystack');

        Route::middleware('tenant.guard')->group(function (): void {
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
                Route::get('brands/{brand}/seo', [SeoController::class, 'showBrand'])->middleware('permission:brands.show')->whereNumber('brand')->name('tenant.brands.seo.show');
                Route::match(['put', 'patch'], 'brands/{brand}/seo', [SeoController::class, 'upsertBrand'])->middleware('permission:brands.update')->whereNumber('brand')->name('tenant.brands.seo.upsert');

                Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('tenant.customers.index');
                Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.show')->whereNumber('customer')->name('tenant.customers.show');
                Route::match(['put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update')->whereNumber('customer')->name('tenant.customers.update');
                Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])->middleware('permission:customers.update')->whereNumber('customer')->name('tenant.customers.status');

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
                Route::get('categories/{category}/seo', [SeoController::class, 'showCategory'])->middleware('permission:categories.show')->whereNumber('category')->name('tenant.categories.seo.show');
                Route::match(['put', 'patch'], 'categories/{category}/seo', [SeoController::class, 'upsertCategory'])->middleware('permission:categories.update')->whereNumber('category')->name('tenant.categories.seo.upsert');

                Route::get('collections', [CollectionController::class, 'index'])->middleware('permission:collections.view')->name('tenant.collections.index');
                Route::post('collections', [CollectionController::class, 'store'])->middleware('permission:collections.create')->name('tenant.collections.store');
                Route::get('collections/{collection}', [CollectionController::class, 'show'])->middleware('permission:collections.show')->whereNumber('collection')->name('tenant.collections.show');
                Route::match(['put', 'patch'], 'collections/{collection}', [CollectionController::class, 'update'])->middleware('permission:collections.update')->whereNumber('collection')->name('tenant.collections.update');
                Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->middleware('permission:collections.delete')->whereNumber('collection')->name('tenant.collections.destroy');
                Route::post('collections/{collection}/products', [CollectionController::class, 'syncProducts'])->middleware('permission:collections.update')->whereNumber('collection')->name('tenant.collections.products.sync');
                Route::get('collections/{collection}/seo', [SeoController::class, 'showCollection'])->middleware('permission:collections.show')->whereNumber('collection')->name('tenant.collections.seo.show');
                Route::match(['put', 'patch'], 'collections/{collection}/seo', [SeoController::class, 'upsertCollection'])->middleware('permission:collections.update')->whereNumber('collection')->name('tenant.collections.seo.upsert');

                Route::get('tags/options', [ProductTagController::class, 'options'])->middleware('permission:tags.view')->name('tenant.tags.options');
                Route::get('tags', [ProductTagController::class, 'index'])->middleware('permission:tags.view')->name('tenant.tags.index');
                Route::post('tags', [ProductTagController::class, 'store'])->middleware('permission:tags.create')->name('tenant.tags.store');
                Route::get('tags/{tag}', [ProductTagController::class, 'show'])->middleware('permission:tags.show')->whereNumber('tag')->name('tenant.tags.show');
                Route::match(['put', 'patch'], 'tags/{tag}', [ProductTagController::class, 'update'])->middleware('permission:tags.update')->whereNumber('tag')->name('tenant.tags.update');
                Route::delete('tags/{tag}', [ProductTagController::class, 'destroy'])->middleware('permission:tags.delete')->whereNumber('tag')->name('tenant.tags.destroy');

                Route::get('badges/options', [ProductBadgeController::class, 'options'])->middleware('permission:badges.view')->name('tenant.badges.options');
                Route::get('badges', [ProductBadgeController::class, 'index'])->middleware('permission:badges.view')->name('tenant.badges.index');
                Route::post('badges', [ProductBadgeController::class, 'store'])->middleware('permission:badges.create')->name('tenant.badges.store');
                Route::get('badges/{badge}', [ProductBadgeController::class, 'show'])->middleware('permission:badges.show')->whereNumber('badge')->name('tenant.badges.show');
                Route::match(['put', 'patch'], 'badges/{badge}', [ProductBadgeController::class, 'update'])->middleware('permission:badges.update')->whereNumber('badge')->name('tenant.badges.update');
                Route::delete('badges/{badge}', [ProductBadgeController::class, 'destroy'])->middleware('permission:badges.delete')->whereNumber('badge')->name('tenant.badges.destroy');

                Route::get('options/options', [ProductOptionController::class, 'options'])->middleware('permission:options.view')->name('tenant.options.options');
                Route::get('options', [ProductOptionController::class, 'index'])->middleware('permission:options.view')->name('tenant.options.index');
                Route::post('options', [ProductOptionController::class, 'store'])->middleware('permission:options.create')->name('tenant.options.store');
                Route::get('options/{option}', [ProductOptionController::class, 'show'])->middleware('permission:options.show')->whereNumber('option')->name('tenant.options.show');
                Route::match(['put', 'patch'], 'options/{option}', [ProductOptionController::class, 'update'])->middleware('permission:options.update')->whereNumber('option')->name('tenant.options.update');
                Route::delete('options/{option}', [ProductOptionController::class, 'destroy'])->middleware('permission:options.delete')->whereNumber('option')->name('tenant.options.destroy');
                Route::post('options/{option}/values', [ProductOptionController::class, 'storeValue'])->middleware('permission:options.update')->whereNumber('option')->name('tenant.options.values.store');
                Route::match(['put', 'patch'], 'options/{option}/values/{value}', [ProductOptionController::class, 'updateValue'])->middleware('permission:options.update')->whereNumber(['option', 'value'])->name('tenant.options.values.update');
                Route::delete('options/{option}/values/{value}', [ProductOptionController::class, 'destroyValue'])->middleware('permission:options.update')->whereNumber(['option', 'value'])->name('tenant.options.values.destroy');

                Route::get('attributes/options', [ProductAttributeController::class, 'options'])->middleware('permission:attributes.view')->name('tenant.attributes.options');
                Route::get('attributes', [ProductAttributeController::class, 'index'])->middleware('permission:attributes.view')->name('tenant.attributes.index');
                Route::post('attributes', [ProductAttributeController::class, 'store'])->middleware('permission:attributes.create')->name('tenant.attributes.store');
                Route::get('attributes/{attribute}', [ProductAttributeController::class, 'show'])->middleware('permission:attributes.show')->whereNumber('attribute')->name('tenant.attributes.show');
                Route::match(['put', 'patch'], 'attributes/{attribute}', [ProductAttributeController::class, 'update'])->middleware('permission:attributes.update')->whereNumber('attribute')->name('tenant.attributes.update');
                Route::delete('attributes/{attribute}', [ProductAttributeController::class, 'destroy'])->middleware('permission:attributes.delete')->whereNumber('attribute')->name('tenant.attributes.destroy');
                Route::post('attributes/{attribute}/values', [ProductAttributeController::class, 'storeValue'])->middleware('permission:attributes.update')->whereNumber('attribute')->name('tenant.attributes.values.store');
                Route::match(['put', 'patch'], 'attributes/{attribute}/values/{value}', [ProductAttributeController::class, 'updateValue'])->middleware('permission:attributes.update')->whereNumber(['attribute', 'value'])->name('tenant.attributes.values.update');
                Route::delete('attributes/{attribute}/values/{value}', [ProductAttributeController::class, 'destroyValue'])->middleware('permission:attributes.update')->whereNumber(['attribute', 'value'])->name('tenant.attributes.values.destroy');

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
                Route::get('products/{product}/seo', [SeoController::class, 'showProduct'])->middleware('permission:products.show')->whereNumber('product')->name('tenant.products.seo.show');
                Route::match(['put', 'patch'], 'products/{product}/seo', [SeoController::class, 'upsertProduct'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.seo.upsert');
                Route::put('products/{product}/tags', [ProductTagController::class, 'syncToProduct'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.tags.sync');
                Route::put('products/{product}/badges', [ProductBadgeController::class, 'syncToProduct'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.badges.sync');
                Route::get('products/{product}/relations/{type}', [ProductRelationController::class, 'show'])->middleware('permission:products.show')->whereNumber('product')->whereIn('type', ['related', 'upsell', 'cross_sell'])->name('tenant.products.relations.show');
                Route::put('products/{product}/relations/{type}', [ProductRelationController::class, 'sync'])->middleware('permission:products.update')->whereNumber('product')->whereIn('type', ['related', 'upsell', 'cross_sell'])->name('tenant.products.relations.sync');
                Route::get('products/{product}/specifications', [ProductSpecificationController::class, 'index'])->middleware('permission:products.show')->whereNumber('product')->name('tenant.products.specifications.index');
                Route::put('products/{product}/specifications', [ProductSpecificationController::class, 'sync'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.specifications.sync');
                Route::get('products/{product}/bundle-items', [ProductBundleController::class, 'index'])->middleware('permission:products.show')->whereNumber('product')->name('tenant.products.bundle-items.index');
                Route::put('products/{product}/bundle-items', [ProductBundleController::class, 'sync'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.bundle-items.sync');

                Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])->middleware('permission:variants.view')->whereNumber('product')->name('tenant.products.variants.index');
                Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->middleware('permission:variants.create')->whereNumber('product')->name('tenant.products.variants.store');
                Route::get('products/{product}/variants/{variant}', [ProductVariantController::class, 'show'])->middleware('permission:variants.show')->whereNumber(['product', 'variant'])->name('tenant.products.variants.show');
                Route::match(['put', 'patch'], 'products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.update');
                Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->middleware('permission:variants.delete')->whereNumber(['product', 'variant'])->name('tenant.products.variants.destroy');
                Route::post('products/{product}/variants/{variant}/image', [ProductVariantController::class, 'storeImage'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.image.store');
                Route::delete('products/{product}/variants/{variant}/image', [ProductVariantController::class, 'destroyImage'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.image.destroy');

                Route::get('reviews', [ProductReviewController::class, 'index'])->middleware('permission:reviews.view')->name('tenant.reviews.index');
                Route::patch('reviews/{review}/status', [ProductReviewController::class, 'moderate'])->middleware('permission:reviews.moderate')->whereNumber('review')->name('tenant.reviews.status');
                Route::delete('reviews/{review}', [ProductReviewController::class, 'destroy'])->middleware('permission:reviews.delete')->whereNumber('review')->name('tenant.reviews.destroy');

                Route::get('inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('tenant.inventory.index');
                Route::get('inventory/{inventory}', [InventoryController::class, 'show'])->middleware('permission:inventory.view')->whereNumber('inventory')->name('tenant.inventory.show');
                Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.adjust');
                Route::post('inventory/{inventory}/reserve', [InventoryController::class, 'reserve'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.reserve');
                Route::post('inventory/{inventory}/release', [InventoryController::class, 'release'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.release');
                Route::post('inventory/{inventory}/transfer', [InventoryController::class, 'transfer'])->middleware('permission:inventory.transfer')->whereNumber('inventory')->name('tenant.inventory.transfer');

                Route::get('orders', [OrderController::class, 'index'])->middleware('permission:orders.view')->name('tenant.orders.index');
                Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.show')->whereNumber('order')->name('tenant.orders.show');
                Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:orders.update')->whereNumber('order')->name('tenant.orders.status');
                Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('permission:orders.cancel')->whereNumber('order')->name('tenant.orders.cancel');

                Route::get('shipping-methods/options', [ShippingMethodController::class, 'options'])->middleware('permission:shipping.view')->name('tenant.shipping-methods.options');
                Route::get('shipping-methods', [ShippingMethodController::class, 'index'])->middleware('permission:shipping.view')->name('tenant.shipping-methods.index');
                Route::post('shipping-methods', [ShippingMethodController::class, 'store'])->middleware('permission:shipping.manage')->name('tenant.shipping-methods.store');
                Route::get('shipping-methods/{shipping_method}', [ShippingMethodController::class, 'show'])->middleware('permission:shipping.view')->whereNumber('shipping_method')->name('tenant.shipping-methods.show');
                Route::match(['put', 'patch'], 'shipping-methods/{shipping_method}', [ShippingMethodController::class, 'update'])->middleware('permission:shipping.manage')->whereNumber('shipping_method')->name('tenant.shipping-methods.update');
                Route::delete('shipping-methods/{shipping_method}', [ShippingMethodController::class, 'destroy'])->middleware('permission:shipping.manage')->whereNumber('shipping_method')->name('tenant.shipping-methods.destroy');

                Route::get('shipments', [ShipmentController::class, 'index'])->middleware('permission:shipments.view')->name('tenant.shipments.index');
                Route::post('shipments', [ShipmentController::class, 'store'])->middleware('permission:shipments.manage')->name('tenant.shipments.store');
                Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->middleware('permission:shipments.view')->whereNumber('shipment')->name('tenant.shipments.show');
                Route::patch('shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])->middleware('permission:shipments.manage')->whereNumber('shipment')->name('tenant.shipments.status');

                Route::get('suppliers/options', [SupplierController::class, 'options'])->middleware('permission:suppliers.view')->name('tenant.suppliers.options');
                Route::get('suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view')->name('tenant.suppliers.index');
                Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create')->name('tenant.suppliers.store');
                Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.show')->whereNumber('supplier')->name('tenant.suppliers.show');
                Route::match(['put', 'patch'], 'suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.update')->whereNumber('supplier')->name('tenant.suppliers.update');
                Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete')->whereNumber('supplier')->name('tenant.suppliers.destroy');

                Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:procurement.view')->name('tenant.purchase-orders.index');
                Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:procurement.create')->name('tenant.purchase-orders.store');
                Route::get('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->middleware('permission:procurement.view')->whereNumber('purchase_order')->name('tenant.purchase-orders.show');
                Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:procurement.approve')->whereNumber('purchase_order')->name('tenant.purchase-orders.approve');
                Route::post('purchase-orders/{purchase_order}/mark-ordered', [PurchaseOrderController::class, 'markOrdered'])->middleware('permission:procurement.update')->whereNumber('purchase_order')->name('tenant.purchase-orders.mark-ordered');
                Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:procurement.receive')->whereNumber('purchase_order')->name('tenant.purchase-orders.receive');

                Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:accounting.view')->name('tenant.accounts.index');
                Route::get('journal-entries', [JournalEntryController::class, 'index'])->middleware('permission:accounting.view')->name('tenant.journal-entries.index');
                Route::get('journal-entries/{journal_entry}', [JournalEntryController::class, 'show'])->middleware('permission:accounting.view')->whereNumber('journal_entry')->name('tenant.journal-entries.show');
                Route::post('journal-entries', [JournalEntryController::class, 'store'])->middleware('permission:journal_entries.create')->name('tenant.journal-entries.store');

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
});
