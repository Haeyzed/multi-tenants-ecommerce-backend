<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Auth\AuthController;
use App\Http\Controllers\Tenant\RBAC\PermissionController;
use App\Http\Controllers\Tenant\RBAC\RoleController;
use App\Http\Controllers\Tenant\Subscription\SubscriptionController;
use App\Http\Controllers\Tenant\User\UserController;
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
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id')."\n";
    })->name('tenant.home');

    Route::prefix('api')->middleware(['api', 'tenant.guard'])->group(function (): void {
        Route::prefix('auth')->name('tenant.auth.')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('logout', [AuthController::class, 'logout'])->name('logout');
                Route::get('me', [AuthController::class, 'me'])->name('me');
                Route::match(['put', 'patch'], 'profile', [AuthController::class, 'updateProfile'])->name('profile');
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
