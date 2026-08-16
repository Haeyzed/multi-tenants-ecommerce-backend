<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureMarketplaceEnabled;
use App\Http\Middleware\SetCustomerGuard;
use App\Http\Middleware\SetLandlordGuard;
use App\Http\Middleware\SetTenantGuard;
use App\Jobs\MarkAbandonedCartsJob;
use App\Models\Landlord\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/landlord.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(function (): void {
            Tenant::query()->cursor()->each(function (Tenant $tenant): void {
                MarkAbandonedCartsJob::dispatch($tenant->getTenantKey());
            });
        })->hourly()->name('mark-abandoned-carts')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'landlord.guard' => SetLandlordGuard::class,
            'tenant.guard' => SetTenantGuard::class,
            'customer.guard' => SetCustomerGuard::class,
            'subscription.active' => EnsureActiveSubscription::class,
            'feature' => EnsureFeature::class,
            'marketplace.enabled' => EnsureMarketplaceEnabled::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'token',
            'secret',
            'otp',
        ]);
    })->create();
