<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landlord (Central) Web Routes
|--------------------------------------------------------------------------
|
| Extra central web routes. The default welcome lives in routes/web.php.
| Bound to configured central domains so they never collide with tenant URIs.
|
*/

foreach (config('tenancy.identification.central_domains') as $index => $domain) {
    $routes = Route::domain($domain)->middleware('central');

    if ($index !== 0) {
        $routes = $routes->name("central{$index}.");
    }

    $routes->group(function (): void {
        // Central web UI routes belong here.
    });
}
