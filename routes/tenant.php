<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Accounting\AccountController;
use App\Http\Controllers\Tenant\Accounting\JournalEntryController;
use App\Http\Controllers\Tenant\Analytics\AnalyticsController;
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
use App\Http\Controllers\Tenant\Commerce\CouponController;
use App\Http\Controllers\Tenant\Commerce\CustomerDeliveryController;
use App\Http\Controllers\Tenant\Commerce\CustomerGiftCardController;
use App\Http\Controllers\Tenant\Commerce\CustomerInvoiceController;
use App\Http\Controllers\Tenant\Commerce\CustomerOrderController;
use App\Http\Controllers\Tenant\Commerce\CustomerOrderReturnController;
use App\Http\Controllers\Tenant\Commerce\CustomerShipmentController;
use App\Http\Controllers\Tenant\Commerce\CustomerStoreCreditController;
use App\Http\Controllers\Tenant\Commerce\CustomerWishlistController;
use App\Http\Controllers\Tenant\Commerce\FlashSaleController;
use App\Http\Controllers\Tenant\Commerce\GiftCardController;
use App\Http\Controllers\Tenant\Commerce\InvoiceController;
use App\Http\Controllers\Tenant\Commerce\OrderController;
use App\Http\Controllers\Tenant\Commerce\OrderReturnController;
use App\Http\Controllers\Tenant\Commerce\PaymentController;
use App\Http\Controllers\Tenant\Commerce\PaymentWebhookController;
use App\Http\Controllers\Tenant\Commerce\PromotionController;
use App\Http\Controllers\Tenant\Commerce\RefundController;
use App\Http\Controllers\Tenant\Commerce\StoreCreditController;
use App\Http\Controllers\Tenant\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Tenant\Customer\CustomerController;
use App\Http\Controllers\Tenant\Customer\CustomerGroupController;
use App\Http\Controllers\Tenant\Customer\CustomerSegmentController;
use App\Http\Controllers\Tenant\Customer\ProductReviewController as CustomerProductReviewController;
use App\Http\Controllers\Tenant\Customer\RecentlyViewedProductController;
use App\Http\Controllers\Tenant\Delivery\DeliveryController;
use App\Http\Controllers\Tenant\Driver\AuthController as DriverAuthController;
use App\Http\Controllers\Tenant\Driver\DriverController;
use App\Http\Controllers\Tenant\Driver\DriverDeliveryController;
use App\Http\Controllers\Tenant\Driver\DriverLocationController;
use App\Http\Controllers\Tenant\HomeController;
use App\Http\Controllers\Tenant\Integration\IntegrationTokenController;
use App\Http\Controllers\Tenant\Inventory\InventoryController;
use App\Http\Controllers\Tenant\Loyalty\CustomerLoyaltyController;
use App\Http\Controllers\Tenant\Loyalty\LoyaltyAccountController;
use App\Http\Controllers\Tenant\Loyalty\LoyaltyProgramController;
use App\Http\Controllers\Tenant\Marketplace\SellerCommissionController;
use App\Http\Controllers\Tenant\Marketplace\SellerController;
use App\Http\Controllers\Tenant\Marketplace\SellerGroupController;
use App\Http\Controllers\Tenant\Marketplace\SellerOfferController;
use App\Http\Controllers\Tenant\Marketplace\SellerOrderController;
use App\Http\Controllers\Tenant\Marketplace\SellerPayoutController;
use App\Http\Controllers\Tenant\Marketplace\SellerProfileController;
use App\Http\Controllers\Tenant\Media\MediaController;
use App\Http\Controllers\Tenant\Notification\DeviceController as NotificationDeviceController;
use App\Http\Controllers\Tenant\Notification\InboxController as NotificationInboxController;
use App\Http\Controllers\Tenant\Notification\PreferenceController as NotificationPreferenceController;
use App\Http\Controllers\Tenant\Payment\PaymentGatewayController;
use App\Http\Controllers\Tenant\Pos\PosCashDrawerController;
use App\Http\Controllers\Tenant\Pos\PosCatalogController;
use App\Http\Controllers\Tenant\Pos\PosReportController;
use App\Http\Controllers\Tenant\Pos\PosSaleController;
use App\Http\Controllers\Tenant\Pos\PosSessionController;
use App\Http\Controllers\Tenant\Pos\PosTerminalController;
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
use App\Http\Controllers\Tenant\Shipping\CarrierWebhookController;
use App\Http\Controllers\Tenant\Shipping\ShipmentController;
use App\Http\Controllers\Tenant\Shipping\ShippingMethodController;
use App\Http\Controllers\Tenant\Storefront\StorefrontBrandController;
use App\Http\Controllers\Tenant\Storefront\StorefrontCategoryController;
use App\Http\Controllers\Tenant\Storefront\StorefrontCollectionController;
use App\Http\Controllers\Tenant\Storefront\StorefrontProductController;
use App\Http\Controllers\Tenant\Storefront\StorefrontRecommendationController;
use App\Http\Controllers\Tenant\Storefront\StorefrontReviewController;
use App\Http\Controllers\Tenant\Subscription\SubscriptionController;
use App\Http\Controllers\Tenant\Tax\TaxController;
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
    Route::get('/', HomeController::class)->name('tenant.home');

    Route::prefix('api')->middleware('api')->group(function (): void {
        Route::prefix('storefront')->name('storefront.')->group(function (): void {
            Route::get('products', [StorefrontProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [StorefrontProductController::class, 'show'])->name('products.show');
            Route::get('products/{product}/reviews', [StorefrontReviewController::class, 'index'])->whereNumber('product')->name('products.reviews.index');
            Route::get('products/{product}/recommendations', [StorefrontRecommendationController::class, 'index'])->whereNumber('product')->name('products.recommendations.index');
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
                Route::get('products/recently-viewed', [RecentlyViewedProductController::class, 'index'])->name('products.recently-viewed');

                Route::get('orders', [CustomerOrderController::class, 'index'])->name('orders.index');
                Route::get('orders/{order}', [CustomerOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
                Route::post('orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->whereNumber('order')->name('orders.cancel');
                Route::get('orders/{order}/refunds', [CustomerOrderController::class, 'refunds'])->whereNumber('order')->name('orders.refunds');
                Route::get('orders/{order}/shipments', [CustomerShipmentController::class, 'index'])->whereNumber('order')->name('orders.shipments.index');
                Route::get('orders/{order}/deliveries', [CustomerDeliveryController::class, 'index'])->whereNumber('order')->name('orders.deliveries.index');
                Route::get('orders/{order}/invoice', [CustomerInvoiceController::class, 'forOrder'])->whereNumber('order')->name('orders.invoice');
                Route::post('orders/{order}/returns', [CustomerOrderReturnController::class, 'store'])->whereNumber('order')->name('orders.returns.store');
                Route::get('returns', [CustomerOrderReturnController::class, 'index'])->name('returns.index');
                Route::get('returns/{order_return}', [CustomerOrderReturnController::class, 'show'])->whereNumber('order_return')->name('returns.show');
                Route::get('invoices/{invoice}', [CustomerInvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
                Route::get('invoices/{invoice}/download', [CustomerInvoiceController::class, 'download'])->whereNumber('invoice')->name('invoices.download');
            });
        });

        Route::prefix('driver')->middleware('driver.guard')->name('driver.')->group(function (): void {
            Route::middleware('throttle:6,1')->group(function (): void {
                Route::post('login', [DriverAuthController::class, 'login'])->name('login');
                Route::post('forgot-password', [DriverAuthController::class, 'forgotPassword'])->name('forgot-password');
                Route::post('reset-password', [DriverAuthController::class, 'resetPassword'])->name('reset-password');
            });

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('logout', [DriverAuthController::class, 'logout'])->name('logout');
                Route::get('me', [DriverAuthController::class, 'me'])->name('me');
                Route::match(['put', 'patch'], 'profile', [DriverAuthController::class, 'updateProfile'])->name('profile');
                Route::post('change-password', [DriverAuthController::class, 'changePassword'])->name('change-password');

                Route::get('deliveries', [DriverDeliveryController::class, 'index'])->name('deliveries.index');
                Route::get('deliveries/{delivery}', [DriverDeliveryController::class, 'show'])->whereNumber('delivery')->name('deliveries.show');
                Route::post('deliveries/{delivery}/accept', [DriverDeliveryController::class, 'accept'])->whereNumber('delivery')->name('deliveries.accept');
                Route::post('deliveries/{delivery}/reject', [DriverDeliveryController::class, 'reject'])->whereNumber('delivery')->name('deliveries.reject');
                Route::post('deliveries/{delivery}/picked-up', [DriverDeliveryController::class, 'markPickedUp'])->whereNumber('delivery')->name('deliveries.picked-up');
                Route::post('deliveries/{delivery}/out-for-delivery', [DriverDeliveryController::class, 'markOutForDelivery'])->whereNumber('delivery')->name('deliveries.out-for-delivery');
                Route::post('deliveries/{delivery}/arrived', [DriverDeliveryController::class, 'markArrived'])->whereNumber('delivery')->name('deliveries.arrived');
                Route::post('deliveries/{delivery}/delivered', [DriverDeliveryController::class, 'markDelivered'])->whereNumber('delivery')->name('deliveries.delivered');
                Route::post('deliveries/{delivery}/failed', [DriverDeliveryController::class, 'markFailed'])->whereNumber('delivery')->name('deliveries.failed');

                Route::post('locations', [DriverLocationController::class, 'store'])->middleware('throttle:60,1')->name('locations.store');
            });
        });

        Route::middleware(['customer.guard', 'auth:sanctum'])->group(function (): void {
            Route::get('wishlist', [CustomerWishlistController::class, 'show'])->name('customer.wishlist.show');
            Route::post('wishlist/items', [CustomerWishlistController::class, 'storeItem'])->name('customer.wishlist.items.store');
            Route::delete('wishlist/items/{item}', [CustomerWishlistController::class, 'destroyItem'])->whereNumber('item')->name('customer.wishlist.items.destroy');
            Route::get('wishlist/check/{product}', [CustomerWishlistController::class, 'check'])->whereNumber('product')->name('customer.wishlist.check');

            Route::get('cart', [CartController::class, 'show'])->name('customer.cart.show');
            Route::delete('cart', [CartController::class, 'destroy'])->name('customer.cart.destroy');
            Route::post('cart/items', [CartController::class, 'storeItem'])->name('customer.cart.items.store');
            Route::patch('cart/items/{item}', [CartController::class, 'updateItem'])->whereNumber('item')->name('customer.cart.items.update');
            Route::delete('cart/items/{item}', [CartController::class, 'destroyItem'])->whereNumber('item')->name('customer.cart.items.destroy');
            Route::post('cart/coupon', [CartController::class, 'applyCoupon'])->name('customer.cart.coupon.apply');
            Route::middleware('feature:loyalty')->group(function (): void {
                Route::get('loyalty/account', [CustomerLoyaltyController::class, 'account'])->name('customer.loyalty.account');
                Route::get('loyalty/transactions', [CustomerLoyaltyController::class, 'transactions'])->name('customer.loyalty.transactions');
                Route::post('loyalty/redemption-preview', [CustomerLoyaltyController::class, 'previewRedemption'])->name('customer.loyalty.redemption-preview');
            });

            Route::middleware('feature:gift-cards')->group(function (): void {
                Route::post('cart/gift-card/preview', [CustomerGiftCardController::class, 'preview'])->name('customer.cart.gift-card.preview');
            });

            Route::middleware('feature:store-credit')->group(function (): void {
                Route::get('store-credit', [CustomerStoreCreditController::class, 'show'])->name('customer.store-credit.show');
                Route::get('store-credit/transactions', [CustomerStoreCreditController::class, 'transactions'])->name('customer.store-credit.transactions');
            });

            Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('customer.checkout.store');
            Route::post('checkout/pay', [PaymentController::class, 'pay'])->middleware('throttle:20,1')->name('customer.checkout.pay');
            Route::post('payments/verify', [PaymentController::class, 'verify'])->middleware('throttle:30,1')->name('customer.payments.verify');
        });

        Route::post('payments/webhooks/paystack', [PaymentWebhookController::class, 'paystack'])
            ->middleware('throttle:120,1')
            ->name('tenant.payments.webhooks.paystack');

        Route::post('payments/webhooks/{provider}', PaymentWebhookController::class)
            ->middleware('throttle:120,1')
            ->where('provider', 'paystack|flutterwave|monnify|moniepoint|fake')
            ->name('tenant.payments.webhooks.provider');

        Route::post('webhooks/shipping/{carrier}', CarrierWebhookController::class)
            ->middleware('throttle:120,1')
            ->name('tenant.webhooks.shipping.carrier');

        Route::middleware('tenant.guard')->group(function (): void {
            Route::prefix('auth')->name('tenant.auth.')->group(function (): void {
                Route::middleware('throttle:6,1')->group(function (): void {
                    Route::post('register', [AuthController::class, 'register'])->name('register');
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

                Route::get('segments', [CustomerSegmentController::class, 'index'])->middleware('permission:segments.view')->name('tenant.segments.index');
                Route::post('segments', [CustomerSegmentController::class, 'store'])->middleware('permission:segments.manage')->name('tenant.segments.store');
                Route::get('segments/{segment}', [CustomerSegmentController::class, 'show'])->middleware('permission:segments.view')->whereNumber('segment')->name('tenant.segments.show');
                Route::match(['put', 'patch'], 'segments/{segment}', [CustomerSegmentController::class, 'update'])->middleware('permission:segments.manage')->whereNumber('segment')->name('tenant.segments.update');
                Route::delete('segments/{segment}', [CustomerSegmentController::class, 'destroy'])->middleware('permission:segments.manage')->whereNumber('segment')->name('tenant.segments.destroy');
                Route::get('segments/{slug}/customers', [CustomerSegmentController::class, 'customers'])->middleware('permission:segments.view')->name('tenant.segments.customers');

                Route::get('customer-groups/options', [CustomerGroupController::class, 'options'])->middleware('permission:customer_groups.view')->name('tenant.customer-groups.options');
                Route::get('customer-groups', [CustomerGroupController::class, 'index'])->middleware('permission:customer_groups.view')->name('tenant.customer-groups.index');
                Route::post('customer-groups', [CustomerGroupController::class, 'store'])->middleware('permission:customer_groups.create')->name('tenant.customer-groups.store');
                Route::get('customer-groups/{customer_group}', [CustomerGroupController::class, 'show'])->middleware('permission:customer_groups.show')->whereNumber('customer_group')->name('tenant.customer-groups.show');
                Route::match(['put', 'patch'], 'customer-groups/{customer_group}', [CustomerGroupController::class, 'update'])->middleware('permission:customer_groups.update')->whereNumber('customer_group')->name('tenant.customer-groups.update');
                Route::delete('customer-groups/{customer_group}', [CustomerGroupController::class, 'destroy'])->middleware('permission:customer_groups.delete')->whereNumber('customer_group')->name('tenant.customer-groups.destroy');

                Route::prefix('analytics')->middleware('feature:advanced-reports')->name('tenant.analytics.')->group(function (): void {
                    Route::get('overview', [AnalyticsController::class, 'overview'])->middleware('permission:analytics.view')->name('overview');
                    Route::get('sales', [AnalyticsController::class, 'sales'])->middleware('permission:analytics.sales')->name('sales');
                    Route::get('sales/breakdown', [AnalyticsController::class, 'salesBreakdown'])->middleware('permission:analytics.sales')->name('sales.breakdown');
                    Route::get('customers', [AnalyticsController::class, 'customers'])->middleware('permission:analytics.customers')->name('customers');
                    Route::get('products', [AnalyticsController::class, 'products'])->middleware('permission:analytics.products')->name('products');
                    Route::get('inventory', [AnalyticsController::class, 'inventory'])->middleware('permission:analytics.inventory')->name('inventory');
                    Route::get('marketplace', [AnalyticsController::class, 'marketplace'])->middleware('permission:analytics.marketplace')->name('marketplace');
                    Route::get('coupons', [AnalyticsController::class, 'coupons'])->middleware('permission:analytics.sales')->name('coupons');
                    Route::get('payments', [AnalyticsController::class, 'payments'])->middleware('permission:analytics.sales')->name('payments');
                });

                Route::prefix('integrations')->middleware('feature:api-access')->name('tenant.integrations.')->group(function (): void {
                    Route::get('tokens', [IntegrationTokenController::class, 'index'])->middleware('permission:integrations.view')->name('tokens.index');
                    Route::post('tokens', [IntegrationTokenController::class, 'store'])->middleware('permission:integrations.create')->name('tokens.store');
                    Route::delete('tokens/{token}', [IntegrationTokenController::class, 'destroy'])->middleware('permission:integrations.delete')->whereNumber('token')->name('tokens.destroy');
                });

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
                Route::post('orders/{order}/invoice', [InvoiceController::class, 'generateForOrder'])->middleware('permission:invoices.generate')->whereNumber('order')->name('tenant.orders.invoice.generate');

                Route::get('returns', [OrderReturnController::class, 'index'])->middleware('permission:returns.view')->name('tenant.returns.index');
                Route::get('returns/{order_return}', [OrderReturnController::class, 'show'])->middleware('permission:returns.view')->whereNumber('order_return')->name('tenant.returns.show');
                Route::post('returns/{order_return}/approve', [OrderReturnController::class, 'approve'])->middleware('permission:returns.approve')->whereNumber('order_return')->name('tenant.returns.approve');
                Route::post('returns/{order_return}/reject', [OrderReturnController::class, 'reject'])->middleware('permission:returns.reject')->whereNumber('order_return')->name('tenant.returns.reject');
                Route::post('returns/{order_return}/receive', [OrderReturnController::class, 'markReceived'])->middleware('permission:returns.inspect')->whereNumber('order_return')->name('tenant.returns.receive');
                Route::post('returns/{order_return}/inspect', [OrderReturnController::class, 'startInspection'])->middleware('permission:returns.inspect')->whereNumber('order_return')->name('tenant.returns.inspect');
                Route::post('return-items/{order_return_item}/inspect', [OrderReturnController::class, 'inspectItem'])->middleware('permission:returns.inspect')->whereNumber('order_return_item')->name('tenant.return-items.inspect');
                Route::post('returns/{order_return}/approve-refund', [OrderReturnController::class, 'approveForRefund'])->middleware('permission:returns.complete')->whereNumber('order_return')->name('tenant.returns.approve-refund');
                Route::post('returns/{order_return}/process-refund', [OrderReturnController::class, 'processRefund'])->middleware('permission:returns.complete')->whereNumber('order_return')->name('tenant.returns.process-refund');

                Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:coupons.view')->name('tenant.coupons.index');
                Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:coupons.create')->name('tenant.coupons.store');
                Route::get('coupons/{coupon}', [CouponController::class, 'show'])->middleware('permission:coupons.view')->whereNumber('coupon')->name('tenant.coupons.show');
                Route::match(['put', 'patch'], 'coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupons.update')->whereNumber('coupon')->name('tenant.coupons.update');
                Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupons.delete')->whereNumber('coupon')->name('tenant.coupons.destroy');

                Route::get('promotions', [PromotionController::class, 'index'])->middleware('permission:promotions.view')->name('tenant.promotions.index');
                Route::post('promotions', [PromotionController::class, 'store'])->middleware('permission:promotions.create')->name('tenant.promotions.store');
                Route::get('promotions/{promotion}', [PromotionController::class, 'show'])->middleware('permission:promotions.view')->whereNumber('promotion')->name('tenant.promotions.show');
                Route::match(['put', 'patch'], 'promotions/{promotion}', [PromotionController::class, 'update'])->middleware('permission:promotions.update')->whereNumber('promotion')->name('tenant.promotions.update');
                Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->middleware('permission:promotions.delete')->whereNumber('promotion')->name('tenant.promotions.destroy');

                Route::get('flash-sales', [FlashSaleController::class, 'index'])->middleware('permission:flash_sales.view')->name('tenant.flash-sales.index');
                Route::post('flash-sales', [FlashSaleController::class, 'store'])->middleware('permission:flash_sales.create')->name('tenant.flash-sales.store');
                Route::get('flash-sales/{flashSale}', [FlashSaleController::class, 'show'])->middleware('permission:flash_sales.view')->whereNumber('flashSale')->name('tenant.flash-sales.show');
                Route::match(['put', 'patch'], 'flash-sales/{flashSale}', [FlashSaleController::class, 'update'])->middleware('permission:flash_sales.update')->whereNumber('flashSale')->name('tenant.flash-sales.update');
                Route::delete('flash-sales/{flashSale}', [FlashSaleController::class, 'destroy'])->middleware('permission:flash_sales.delete')->whereNumber('flashSale')->name('tenant.flash-sales.destroy');
                Route::post('flash-sales/{flashSale}/items', [FlashSaleController::class, 'storeItem'])->middleware('permission:flash_sales.update')->whereNumber('flashSale')->name('tenant.flash-sales.items.store');
                Route::match(['put', 'patch'], 'flash-sales/{flashSale}/items/{flashSaleItem}', [FlashSaleController::class, 'updateItem'])->middleware('permission:flash_sales.update')->whereNumber('flashSale')->whereNumber('flashSaleItem')->name('tenant.flash-sales.items.update');
                Route::delete('flash-sales/{flashSale}/items/{flashSaleItem}', [FlashSaleController::class, 'destroyItem'])->middleware('permission:flash_sales.update')->whereNumber('flashSale')->whereNumber('flashSaleItem')->name('tenant.flash-sales.items.destroy');

                Route::middleware('feature:loyalty')->group(function (): void {
                    Route::get('loyalty/program', [LoyaltyProgramController::class, 'show'])->middleware('permission:loyalty.view')->name('tenant.loyalty.program.show');
                    Route::match(['put', 'patch'], 'loyalty/program', [LoyaltyProgramController::class, 'update'])->middleware('permission:loyalty.manage')->name('tenant.loyalty.program.update');
                    Route::get('loyalty/accounts', [LoyaltyAccountController::class, 'index'])->middleware('permission:loyalty.view')->name('tenant.loyalty.accounts.index');
                    Route::get('loyalty/accounts/{loyalty_account}/transactions', [LoyaltyAccountController::class, 'transactions'])->middleware('permission:loyalty.view')->whereNumber('loyalty_account')->name('tenant.loyalty.accounts.transactions');
                    Route::post('loyalty/accounts/{loyalty_account}/adjustments', [LoyaltyAccountController::class, 'storeAdjustment'])->middleware('permission:loyalty.manage')->whereNumber('loyalty_account')->name('tenant.loyalty.accounts.adjustments.store');
                });

                Route::middleware('feature:gift-cards')->group(function (): void {
                    Route::get('gift-cards', [GiftCardController::class, 'index'])->middleware('permission:gift_cards.view')->name('tenant.gift-cards.index');
                    Route::post('gift-cards', [GiftCardController::class, 'store'])->middleware('permission:gift_cards.create')->name('tenant.gift-cards.store');
                    Route::get('gift-cards/{gift_card}', [GiftCardController::class, 'show'])->middleware('permission:gift_cards.view')->whereNumber('gift_card')->name('tenant.gift-cards.show');
                    Route::match(['put', 'patch'], 'gift-cards/{gift_card}', [GiftCardController::class, 'update'])->middleware('permission:gift_cards.update')->whereNumber('gift_card')->name('tenant.gift-cards.update');
                    Route::post('gift-cards/{gift_card}/activate', [GiftCardController::class, 'activate'])->middleware('permission:gift_cards.update')->whereNumber('gift_card')->name('tenant.gift-cards.activate');
                    Route::post('gift-cards/{gift_card}/cancel', [GiftCardController::class, 'cancel'])->middleware('permission:gift_cards.cancel')->whereNumber('gift_card')->name('tenant.gift-cards.cancel');
                });

                Route::middleware('feature:store-credit')->group(function (): void {
                    Route::get('store-credit/accounts', [StoreCreditController::class, 'index'])->middleware('permission:store_credit.view')->name('tenant.store-credit.accounts.index');
                    Route::get('store-credit/customers/{customer}', [StoreCreditController::class, 'show'])->middleware('permission:store_credit.view')->whereNumber('customer')->name('tenant.store-credit.customers.show');
                    Route::get('store-credit/customers/{customer}/transactions', [StoreCreditController::class, 'transactions'])->middleware('permission:store_credit.view')->whereNumber('customer')->name('tenant.store-credit.customers.transactions');
                    Route::post('store-credit/customers/{customer}/credit', [StoreCreditController::class, 'credit'])->middleware('permission:store_credit.manage')->whereNumber('customer')->name('tenant.store-credit.customers.credit');
                    Route::post('store-credit/customers/{customer}/debit', [StoreCreditController::class, 'debit'])->middleware('permission:store_credit.manage')->whereNumber('customer')->name('tenant.store-credit.customers.debit');
                });

                Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:invoices.view')->name('tenant.invoices.index');
                Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices.view')->whereNumber('invoice')->name('tenant.invoices.show');
                Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->middleware('permission:invoices.download')->whereNumber('invoice')->name('tenant.invoices.download');

                Route::get('refunds', [RefundController::class, 'index'])->middleware('permission:refunds.view')->name('tenant.refunds.index');
                Route::post('orders/{order}/refunds', [RefundController::class, 'store'])->middleware('permission:refunds.create')->whereNumber('order')->name('tenant.orders.refunds.store');
                Route::get('refunds/{refund}', [RefundController::class, 'show'])->middleware('permission:refunds.view')->whereNumber('refund')->name('tenant.refunds.show');

                Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])
                    ->middleware('permission:payments.manage|payment_gateways.view|payment_gateways.manage')
                    ->name('tenant.payment-gateways.index');
                Route::put('payment-gateways', [PaymentGatewayController::class, 'upsert'])
                    ->middleware('permission:payments.manage|payment_gateways.manage')
                    ->name('tenant.payment-gateways.upsert');
                Route::post('payment-gateways/{gateway}/enable', [PaymentGatewayController::class, 'enable'])
                    ->middleware('permission:payments.manage|payment_gateways.manage')
                    ->name('tenant.payment-gateways.enable');
                Route::post('payment-gateways/{gateway}/disable', [PaymentGatewayController::class, 'disable'])
                    ->middleware('permission:payments.manage|payment_gateways.manage')
                    ->name('tenant.payment-gateways.disable');

                Route::get('taxes', [TaxController::class, 'index'])->middleware('permission:taxes.view')->name('tenant.taxes.index');
                Route::post('taxes', [TaxController::class, 'store'])->middleware('permission:taxes.create')->name('tenant.taxes.store');
                Route::get('taxes/{tax}', [TaxController::class, 'show'])->middleware('permission:taxes.view')->whereNumber('tax')->name('tenant.taxes.show');
                Route::match(['put', 'patch'], 'taxes/{tax}', [TaxController::class, 'update'])->middleware('permission:taxes.update')->whereNumber('tax')->name('tenant.taxes.update');
                Route::delete('taxes/{tax}', [TaxController::class, 'destroy'])->middleware('permission:taxes.delete')->whereNumber('tax')->name('tenant.taxes.destroy');
                Route::get('tax-zones', [TaxController::class, 'indexZones'])->middleware('permission:taxes.view')->name('tenant.tax-zones.index');
                Route::post('tax-zones', [TaxController::class, 'storeZone'])->middleware('permission:taxes.create')->name('tenant.tax-zones.store');
                Route::get('tax-zones/{tax_zone}', [TaxController::class, 'showZone'])->middleware('permission:taxes.view')->whereNumber('tax_zone')->name('tenant.tax-zones.show');
                Route::match(['put', 'patch'], 'tax-zones/{tax_zone}', [TaxController::class, 'updateZone'])->middleware('permission:taxes.update')->whereNumber('tax_zone')->name('tenant.tax-zones.update');
                Route::delete('tax-zones/{tax_zone}', [TaxController::class, 'destroyZone'])->middleware('permission:taxes.delete')->whereNumber('tax_zone')->name('tenant.tax-zones.destroy');
                Route::post('tax-rules', [TaxController::class, 'storeRule'])->middleware('permission:taxes.create')->name('tenant.tax-rules.store');
                Route::delete('tax-rules/{tax_rule}', [TaxController::class, 'destroyRule'])->middleware('permission:taxes.delete')->whereNumber('tax_rule')->name('tenant.tax-rules.destroy');

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

                Route::get('drivers', [DriverController::class, 'index'])->middleware('permission:drivers.view')->name('tenant.drivers.index');
                Route::post('drivers', [DriverController::class, 'store'])->middleware('permission:drivers.create')->name('tenant.drivers.store');
                Route::get('drivers/{driver}', [DriverController::class, 'show'])->middleware('permission:drivers.show')->whereNumber('driver')->name('tenant.drivers.show');
                Route::match(['put', 'patch'], 'drivers/{driver}', [DriverController::class, 'update'])->middleware('permission:drivers.update')->whereNumber('driver')->name('tenant.drivers.update');
                Route::delete('drivers/{driver}', [DriverController::class, 'destroy'])->middleware('permission:drivers.delete')->whereNumber('driver')->name('tenant.drivers.destroy');

                Route::get('deliveries', [DeliveryController::class, 'index'])->middleware('permission:deliveries.view')->name('tenant.deliveries.index');
                Route::post('deliveries', [DeliveryController::class, 'store'])->middleware('permission:deliveries.manage')->name('tenant.deliveries.store');
                Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->middleware('permission:deliveries.view')->whereNumber('delivery')->name('tenant.deliveries.show');
                Route::post('deliveries/{delivery}/assign', [DeliveryController::class, 'assign'])->middleware('permission:deliveries.manage')->whereNumber('delivery')->name('tenant.deliveries.assign');
                Route::post('deliveries/{delivery}/assign-automatic', [DeliveryController::class, 'assignAutomatic'])->middleware('permission:deliveries.manage')->whereNumber('delivery')->name('tenant.deliveries.assign-automatic');
                Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->middleware('permission:deliveries.manage')->whereNumber('delivery')->name('tenant.deliveries.cancel');
                Route::post('deliveries/{delivery}/fail', [DeliveryController::class, 'fail'])->middleware('permission:deliveries.manage')->whereNumber('delivery')->name('tenant.deliveries.fail');

                Route::middleware('feature:pos')->prefix('pos')->group(function (): void {
                    Route::get('terminals/options', [PosTerminalController::class, 'options'])->middleware('permission:pos.view')->name('tenant.pos.terminals.options');
                    Route::get('terminals', [PosTerminalController::class, 'index'])->middleware('permission:pos.view')->name('tenant.pos.terminals.index');
                    Route::post('terminals', [PosTerminalController::class, 'store'])->middleware('permission:pos.terminals.manage')->name('tenant.pos.terminals.store');
                    Route::get('terminals/{pos_terminal}', [PosTerminalController::class, 'show'])->middleware('permission:pos.view')->whereNumber('pos_terminal')->name('tenant.pos.terminals.show');
                    Route::match(['put', 'patch'], 'terminals/{pos_terminal}', [PosTerminalController::class, 'update'])->middleware('permission:pos.terminals.manage')->whereNumber('pos_terminal')->name('tenant.pos.terminals.update');
                    Route::delete('terminals/{pos_terminal}', [PosTerminalController::class, 'destroy'])->middleware('permission:pos.terminals.manage')->whereNumber('pos_terminal')->name('tenant.pos.terminals.destroy');

                    Route::get('sessions', [PosSessionController::class, 'index'])->middleware('permission:pos.view')->name('tenant.pos.sessions.index');
                    Route::post('sessions/open', [PosSessionController::class, 'open'])->middleware('permission:pos.session.open')->name('tenant.pos.sessions.open');
                    Route::get('sessions/{pos_session}', [PosSessionController::class, 'show'])->middleware('permission:pos.view')->whereNumber('pos_session')->name('tenant.pos.sessions.show');
                    Route::post('sessions/{pos_session}/close', [PosSessionController::class, 'close'])->middleware('permission:pos.session.close')->whereNumber('pos_session')->name('tenant.pos.sessions.close');

                    Route::post('sessions/{pos_session}/cash-in', [PosCashDrawerController::class, 'cashIn'])->middleware('permission:pos.cash_in')->whereNumber('pos_session')->name('tenant.pos.sessions.cash-in');
                    Route::post('sessions/{pos_session}/cash-out', [PosCashDrawerController::class, 'cashOut'])->middleware('permission:pos.cash_out')->whereNumber('pos_session')->name('tenant.pos.sessions.cash-out');

                    Route::post('sessions/{pos_session}/sales', [PosSaleController::class, 'store'])->middleware('permission:pos.sell')->whereNumber('pos_session')->name('tenant.pos.sales.store');
                    Route::post('orders/{order}/pos-refund', [PosSaleController::class, 'refund'])->middleware('permission:pos.refund')->whereNumber('order')->name('tenant.pos.sales.refund');

                    Route::get('catalog/search', [PosCatalogController::class, 'search'])->middleware('permission:pos.view')->name('tenant.pos.catalog.search');
                    Route::get('catalog/barcode', [PosCatalogController::class, 'barcode'])->middleware('permission:pos.view')->name('tenant.pos.catalog.barcode');

                    Route::get('reports/sessions/{pos_session}', [PosReportController::class, 'sessionSummary'])->middleware('permission:pos.reports.view')->whereNumber('pos_session')->name('tenant.pos.reports.session');
                    Route::get('reports/sales-by-terminal', [PosReportController::class, 'salesByTerminal'])->middleware('permission:pos.reports.view')->name('tenant.pos.reports.sales-by-terminal');
                    Route::get('reports/sales-by-cashier', [PosReportController::class, 'salesByCashier'])->middleware('permission:pos.reports.view')->name('tenant.pos.reports.sales-by-cashier');
                    Route::get('reports/payment-methods', [PosReportController::class, 'paymentMethodTotals'])->middleware('permission:pos.reports.view')->name('tenant.pos.reports.payment-methods');
                });

                Route::middleware('marketplace.enabled')->group(function (): void {
                    Route::middleware('seller.user')->group(function (): void {
                        Route::get('seller/profile', [SellerProfileController::class, 'show'])->name('tenant.seller.profile.show');
                        Route::match(['put', 'patch'], 'seller/profile', [SellerProfileController::class, 'update'])->name('tenant.seller.profile.update');
                    });

                    Route::get('seller-groups/options', [SellerGroupController::class, 'options'])->middleware('permission:seller_groups.view')->name('tenant.seller-groups.options');
                    Route::get('seller-groups', [SellerGroupController::class, 'index'])->middleware('permission:seller_groups.view')->name('tenant.seller-groups.index');
                    Route::post('seller-groups', [SellerGroupController::class, 'store'])->middleware('permission:seller_groups.create')->name('tenant.seller-groups.store');
                    Route::get('seller-groups/{seller_group}', [SellerGroupController::class, 'show'])->middleware('permission:seller_groups.show')->whereNumber('seller_group')->name('tenant.seller-groups.show');
                    Route::match(['put', 'patch'], 'seller-groups/{seller_group}', [SellerGroupController::class, 'update'])->middleware('permission:seller_groups.update')->whereNumber('seller_group')->name('tenant.seller-groups.update');
                    Route::delete('seller-groups/{seller_group}', [SellerGroupController::class, 'destroy'])->middleware('permission:seller_groups.delete')->whereNumber('seller_group')->name('tenant.seller-groups.destroy');

                    Route::get('sellers', [SellerController::class, 'index'])->middleware('permission:sellers.view')->name('tenant.sellers.index');
                    Route::post('sellers', [SellerController::class, 'store'])->middleware('permission:sellers.create')->name('tenant.sellers.store');
                    Route::get('sellers/{seller}', [SellerController::class, 'show'])->middleware('permission:sellers.view')->whereNumber('seller')->name('tenant.sellers.show');
                    Route::match(['put', 'patch'], 'sellers/{seller}', [SellerController::class, 'update'])->middleware('permission:sellers.update')->whereNumber('seller')->name('tenant.sellers.update');
                    Route::patch('sellers/{seller}/approve', [SellerController::class, 'approve'])->middleware('permission:sellers.approve')->whereNumber('seller')->name('tenant.sellers.approve');
                    Route::patch('sellers/{seller}/reject', [SellerController::class, 'reject'])->middleware('permission:sellers.reject')->whereNumber('seller')->name('tenant.sellers.reject');
                    Route::patch('sellers/{seller}/suspend', [SellerController::class, 'suspend'])->middleware('permission:sellers.suspend')->whereNumber('seller')->name('tenant.sellers.suspend');
                    Route::patch('sellers/{seller}/activate', [SellerController::class, 'activate'])->middleware('permission:sellers.update')->whereNumber('seller')->name('tenant.sellers.activate');

                    Route::get('seller-offers', [SellerOfferController::class, 'index'])->middleware('permission:seller_offers.view')->name('tenant.seller-offers.index');
                    Route::post('seller-offers', [SellerOfferController::class, 'store'])->middleware('permission:seller_offers.create')->name('tenant.seller-offers.store');
                    Route::get('seller-offers/{seller_offer}', [SellerOfferController::class, 'show'])->middleware('permission:seller_offers.view')->whereNumber('seller_offer')->name('tenant.seller-offers.show');
                    Route::match(['put', 'patch'], 'seller-offers/{seller_offer}', [SellerOfferController::class, 'update'])->middleware('permission:seller_offers.update')->whereNumber('seller_offer')->name('tenant.seller-offers.update');
                    Route::delete('seller-offers/{seller_offer}', [SellerOfferController::class, 'destroy'])->middleware('permission:seller_offers.delete')->whereNumber('seller_offer')->name('tenant.seller-offers.destroy');

                    Route::get('seller-orders', [SellerOrderController::class, 'index'])->middleware('permission:seller_orders.view')->name('tenant.seller-orders.index');
                    Route::get('seller-orders/{seller_order}', [SellerOrderController::class, 'show'])->middleware('permission:seller_orders.view')->whereNumber('seller_order')->name('tenant.seller-orders.show');
                    Route::patch('seller-orders/{seller_order}/status', [SellerOrderController::class, 'updateStatus'])->middleware('permission:seller_orders.manage')->whereNumber('seller_order')->name('tenant.seller-orders.status');

                    Route::get('commissions', [SellerCommissionController::class, 'index'])->middleware('permission:commissions.view')->name('tenant.commissions.index');
                    Route::get('commissions/{commission}', [SellerCommissionController::class, 'show'])->middleware('permission:commissions.view')->whereNumber('commission')->name('tenant.commissions.show');

                    Route::get('payouts', [SellerPayoutController::class, 'index'])->middleware('permission:payouts.view')->name('tenant.payouts.index');
                    Route::post('payouts', [SellerPayoutController::class, 'store'])->middleware('permission:payouts.manage')->name('tenant.payouts.store');
                    Route::get('payouts/{payout}', [SellerPayoutController::class, 'show'])->middleware('permission:payouts.view')->whereNumber('payout')->name('tenant.payouts.show');
                });

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
