<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\HomeController;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Http\Middleware\PreventAccessFromUnwantedDomainsUnlessTenantHeader;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Loaded by TenancyServiceProvider with the "tenant" route-mode group.
| Identification middleware lives here because that group is a Stancl v4
| marker, not the domain initializer itself.
|
*/

Route::middleware([
    InitializeTenancyByDomainOrHeader::class,
    PreventAccessFromUnwantedDomainsUnlessTenantHeader::class,
])->group(function (): void {
    Route::get('/', HomeController::class)->name('tenant.home');

    Route::prefix('api')->middleware('api')->group(function (): void {
        require __DIR__.'/tenant/storefront.php';
        require __DIR__.'/tenant/public.php';
        require __DIR__.'/tenant/customer.php';
        require __DIR__.'/tenant/driver.php';
        require __DIR__.'/tenant/seller.php';
        require __DIR__.'/tenant/webhooks.php';

        Route::middleware('tenant.guard')->group(function (): void {
            require __DIR__.'/tenant/auth.php';

            Route::middleware(['auth:tenant', 'subscription.active'])->group(function (): void {
                require __DIR__.'/tenant/users.php';
                require __DIR__.'/tenant/rbac.php';
                require __DIR__.'/tenant/media.php';
                require __DIR__.'/tenant/brands.php';
                require __DIR__.'/tenant/customers.php';
                require __DIR__.'/tenant/analytics.php';
                require __DIR__.'/tenant/integrations.php';
                require __DIR__.'/tenant/catalog.php';
                require __DIR__.'/tenant/products.php';
                require __DIR__.'/tenant/orders.php';
                require __DIR__.'/tenant/commerce.php';
                require __DIR__.'/tenant/tax.php';
                require __DIR__.'/tenant/shipping.php';
                require __DIR__.'/tenant/drivers.php';
                require __DIR__.'/tenant/settings.php';
                require __DIR__.'/tenant/hr.php';
                require __DIR__.'/tenant/content.php';
                require __DIR__.'/tenant/deliveries.php';
                require __DIR__.'/tenant/pos.php';
                require __DIR__.'/tenant/marketplace.php';
                require __DIR__.'/tenant/procurement.php';
                require __DIR__.'/tenant/accounting.php';
                require __DIR__.'/tenant/notifications.php';
            });
        });
    });
});
