<?php

declare(strict_types=1);

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

foreach (config('tenancy.identification.central_domains') as $domain) {
    Route::domain($domain)
        ->middleware('central')
        ->group(function () {
            Route::get('/', function () {
                return view('welcome');
            })->name('landlord.home');
        });
}
