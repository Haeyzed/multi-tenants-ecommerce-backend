<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Auth\AuthController;
use App\Http\Controllers\Tenant\Subscription\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('tenant.auth.')->group(function (): void {
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });
    Route::middleware('auth:tenant')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::match(['put', 'patch'], 'profile', [AuthController::class, 'updateProfile'])->name('profile');
        Route::post('avatar', [AuthController::class, 'storeAvatar'])->name('avatar.store');
        Route::delete('avatar', [AuthController::class, 'destroyAvatar'])->name('avatar.destroy');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
    });
});

Route::middleware('auth:tenant')->group(function (): void {
    Route::prefix('subscription')->name('tenant.subscription.')->group(function (): void {
        Route::get('/', [SubscriptionController::class, 'current'])->name('current');
        Route::post('subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::post('verify', [SubscriptionController::class, 'verify'])->name('verify');
        Route::post('cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('change-plan', [SubscriptionController::class, 'changePlan'])->name('change-plan');
    });
});
