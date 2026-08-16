<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landlord (Central) Routes
|--------------------------------------------------------------------------
|
| Routes for the central / landlord application. These run against the
| central database and are not initialized in a tenant context.
|
| Bound to configured central domains so they do not collide with tenant
| routes that share the same URI (e.g. "/").
|
*/

foreach (config('tenancy.identification.central_domains') as $index => $domain) {
    // Canonical names on the primary central domain only; prefix secondary domains
    // so `php artisan route:cache` can serialize multi-domain central routes.
    if ($index === 0) {
        Route::domain($domain)
            ->middleware('central')
            ->group(function (): void {
                Route::get('/', HomeController::class)->name('landlord.home');
            });

        continue;
    }

    Route::domain($domain)
        ->middleware('central')
        ->name("central{$index}.")
        ->group(function (): void {
            Route::get('/', HomeController::class)->name('landlord.home');
        });
}
