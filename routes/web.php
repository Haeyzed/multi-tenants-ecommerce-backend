<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Default Laravel welcome for the central application. Bound to central
| domains so tenant `/` in routes/tenant.php stays reachable on tenant hosts.
|
*/

foreach (config('tenancy.identification.central_domains') as $index => $domain) {
    $routes = Route::domain($domain)->middleware('central');

    if ($index !== 0) {
        $routes = $routes->name("central{$index}.");
    }

    $routes->group(function (): void {
        Route::get('/', function () {
            return view('welcome');
        });
    });
}

require __DIR__.'/landlord.php';
