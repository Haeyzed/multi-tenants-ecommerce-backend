<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Driver\AuthController as DriverAuthController;
use App\Http\Controllers\Tenant\Driver\DriverDeliveryController;
use App\Http\Controllers\Tenant\Driver\DriverLocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('driver')->middleware('driver.guard')->name('driver.')->group(function (): void {
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('login', [DriverAuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [DriverAuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [DriverAuthController::class, 'resetPassword'])->name('reset-password');
    });

    Route::middleware('auth:driver')->group(function (): void {
        Route::post('logout', [DriverAuthController::class, 'logout'])->name('logout');
        Route::get('me', [DriverAuthController::class, 'me'])->name('me');
        Route::match(['put', 'patch'], 'profile', [DriverAuthController::class, 'updateProfile'])->name('profile');
        Route::post('change-password', [DriverAuthController::class, 'changePassword'])->name('change-password');

        Route::get('deliveries', [DriverDeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/{delivery}', [DriverDeliveryController::class, 'show'])->whereNumber('delivery')->name('deliveries.show');
        Route::post('deliveries/{delivery}/accept', [DriverDeliveryController::class, 'accept'])->whereNumber('delivery')->name('deliveries.accept');
        Route::post('deliveries/{delivery}/reject', [DriverDeliveryController::class, 'reject'])->whereNumber('delivery')->name('deliveries.reject');
        Route::post('deliveries/{delivery}/picked-up', [DriverDeliveryController::class, 'markPickedUp'])->whereNumber('delivery')->name('deliveries.picked-up');
        Route::post('deliveries/{delivery}/out-for-delivery', [DriverDeliveryController::class, 'markOutForDelivery'])->whereNumber('delivery')->name('deliveries.out-for-delivery');
        Route::post('deliveries/{delivery}/arrived', [DriverDeliveryController::class, 'markArrived'])->whereNumber('delivery')->name('deliveries.arrived');
        Route::post('deliveries/{delivery}/delivered', [DriverDeliveryController::class, 'markDelivered'])->whereNumber('delivery')->name('deliveries.delivered');
        Route::post('deliveries/{delivery}/failed', [DriverDeliveryController::class, 'markFailed'])->whereNumber('delivery')->name('deliveries.failed');

        Route::post('locations', [DriverLocationController::class, 'store'])->middleware('throttle:60,1')->name('locations.store');
    });
});
