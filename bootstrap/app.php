<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureMarketplaceEnabled;
use App\Http\Middleware\EnsureSellerUser;
use App\Http\Middleware\SetCustomerGuard;
use App\Http\Middleware\SetDriverGuard;
use App\Http\Middleware\SetLandlordGuard;
use App\Http\Middleware\SetTenantGuard;
use App\Jobs\CleanupDriverLocationsJob;
use App\Jobs\MarkAbandonedCartsJob;
use App\Jobs\ReconcileProcessingRefundsJob;
use App\Jobs\RefreshCustomerSegmentStatsJob;
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
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(function (): void {
            Tenant::query()->cursor()->each(function (Tenant $tenant): void {
                MarkAbandonedCartsJob::dispatch($tenant->getTenantKey());
            });
        })->hourly()->name('mark-abandoned-carts')->withoutOverlapping();

        $schedule->call(function (): void {
            Tenant::query()->cursor()->each(function (Tenant $tenant): void {
                ReconcileProcessingRefundsJob::dispatch($tenant->getTenantKey());
            });
        })->everyFifteenMinutes()->name('reconcile-processing-refunds')->withoutOverlapping();

        $schedule->call(function (): void {
            Tenant::query()->cursor()->each(function (Tenant $tenant): void {
                RefreshCustomerSegmentStatsJob::dispatch($tenant->getTenantKey());
            });
        })->hourly()->name('refresh-customer-segment-stats')->withoutOverlapping();

        $schedule->call(function (): void {
            Tenant::query()->cursor()->each(function (Tenant $tenant): void {
                CleanupDriverLocationsJob::dispatch($tenant->getTenantKey());
            });
        })->daily()->name('cleanup-driver-locations')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'landlord.guard' => SetLandlordGuard::class,
            'tenant.guard' => SetTenantGuard::class,
            'customer.guard' => SetCustomerGuard::class,
            'driver.guard' => SetDriverGuard::class,
            'subscription.active' => EnsureActiveSubscription::class,
            'feature' => EnsureFeature::class,
            'marketplace.enabled' => EnsureMarketplaceEnabled::class,
            'seller.user' => EnsureSellerUser::class,
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
