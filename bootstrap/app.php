<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureHrEnabled;
use App\Http\Middleware\EnsureMarketplaceEnabled;
use App\Http\Middleware\EnsurePublicRecruitmentEnabled;
use App\Http\Middleware\SetCustomerGuard;
use App\Http\Middleware\SetDriverGuard;
use App\Http\Middleware\SetLandlordGuard;
use App\Http\Middleware\SetSellerGuard;
use App\Http\Middleware\SetTenantGuard;
use App\Jobs\CleanupDriverLocationsJob;
use App\Jobs\EnsureCurrentPayrollPeriodJob;
use App\Jobs\MarkAbandonedCartsJob;
use App\Jobs\ProcessSubscriptionLifecycleJob;
use App\Jobs\ReconcileProcessingRefundsJob;
use App\Jobs\RefreshCustomerSegmentStatsJob;
use App\Jobs\SendInterviewRemindersJob;
use App\Models\Landlord\Tenant;
use Illuminate\Auth\Middleware\Authenticate;
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
        web: __DIR__.'/../routes/web.php',
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

        $schedule->call(function (): void {
            Tenant::query()->cursor()->each(function (Tenant $tenant): void {
                EnsureCurrentPayrollPeriodJob::dispatch($tenant->getTenantKey());
            });
        })->daily()->name('ensure-current-payroll-period')->withoutOverlapping();

        $schedule->call(function (): void {
            Tenant::query()->cursor()->each(function (Tenant $tenant): void {
                SendInterviewRemindersJob::dispatch($tenant->getTenantKey());
            });
        })->hourly()->name('send-interview-reminders')->withoutOverlapping();

        $schedule->job(new ProcessSubscriptionLifecycleJob)
            ->daily()
            ->name('process-subscription-lifecycle')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'landlord.guard' => SetLandlordGuard::class,
            'tenant.guard' => SetTenantGuard::class,
            'customer.guard' => SetCustomerGuard::class,
            'driver.guard' => SetDriverGuard::class,
            'seller.guard' => SetSellerGuard::class,
            'subscription.active' => EnsureActiveSubscription::class,
            'feature' => EnsureFeature::class,
            'marketplace.enabled' => EnsureMarketplaceEnabled::class,
            'hr.enabled' => EnsureHrEnabled::class,
            'hr.recruitment.public' => EnsurePublicRecruitmentEnabled::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Guard setters must run before Authenticate so auth:landlord / auth:tenant
        // (and the other named Sanctum guards) resolve tokens against the right provider.
        foreach ([
            SetLandlordGuard::class,
            SetTenantGuard::class,
            SetCustomerGuard::class,
            SetDriverGuard::class,
            SetSellerGuard::class,
        ] as $guardMiddleware) {
            $middleware->prependToPriorityList(
                before: Authenticate::class,
                prepend: $guardMiddleware,
            );
        }
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
