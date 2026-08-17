<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landlord (Central) API Routes
|--------------------------------------------------------------------------
|
| Loaded by bootstrap/app.php with the api prefix and api middleware.
| Bound to central domains so tenant routes with the same URI cannot overwrite
| them, and so Scramble can keep landlord docs on the app host.
|
*/

$registerLandlordApi = function (): void {
    Route::middleware(['landlord.guard', 'central'])->group(function (): void {
        require __DIR__.'/landlord/auth.php';

        Route::middleware('auth:landlord')->group(function (): void {
            require __DIR__.'/landlord/users.php';
            require __DIR__.'/landlord/rbac.php';
            require __DIR__.'/landlord/tenants.php';
            require __DIR__.'/landlord/plans.php';
            require __DIR__.'/landlord/settings.php';
            require __DIR__.'/landlord/cms.php';
            require __DIR__.'/landlord/media.php';
            require __DIR__.'/landlord/notifications.php';
        });
    });

    Route::middleware('central')->group(function (): void {
        require __DIR__.'/landlord/public.php';
        require __DIR__.'/landlord/webhooks.php';
        require __DIR__.'/landlord/world.php';
    });
};

foreach (config('tenancy.identification.central_domains', []) as $index => $domain) {
    if ($index === 0) {
        Route::domain($domain)->group($registerLandlordApi);

        continue;
    }

    Route::domain($domain)->name("central{$index}.")->group($registerLandlordApi);
}
