<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Marketplace\SellerCommissionController;
use App\Http\Controllers\Tenant\Marketplace\SellerOfferController;
use App\Http\Controllers\Tenant\Marketplace\SellerOrderController;
use App\Http\Controllers\Tenant\Marketplace\SellerPayoutController;
use App\Http\Controllers\Tenant\Seller\AuthController as SellerAuthController;
use App\Http\Controllers\Tenant\Seller\ProfileController as SellerProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->middleware(['marketplace.enabled', 'seller.guard'])->name('seller.')->group(function (): void {
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('register', [SellerAuthController::class, 'register'])->name('register');
        Route::post('login', [SellerAuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [SellerAuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [SellerAuthController::class, 'resetPassword'])->name('reset-password');
    });

    Route::middleware('auth:seller')->group(function (): void {
        Route::post('logout', [SellerAuthController::class, 'logout'])->name('logout');
        Route::post('change-password', [SellerAuthController::class, 'changePassword'])->name('change-password');

        Route::get('me', [SellerProfileController::class, 'me'])->name('me');
        Route::match(['put', 'patch'], 'profile', [SellerProfileController::class, 'update'])->name('profile');
        Route::post('logo', [SellerProfileController::class, 'storeLogo'])->name('logo.store');
        Route::delete('logo', [SellerProfileController::class, 'destroyLogo'])->name('logo.destroy');

        // Self-scoped marketplace APIs (authorization via Seller policies, not Spatie tenant admin perms).
        Route::get('offers', [SellerOfferController::class, 'index'])->name('offers.index');
        Route::post('offers', [SellerOfferController::class, 'store'])->name('offers.store');
        Route::get('offers/{seller_offer}', [SellerOfferController::class, 'show'])->whereNumber('seller_offer')->name('offers.show');
        Route::match(['put', 'patch'], 'offers/{seller_offer}', [SellerOfferController::class, 'update'])->whereNumber('seller_offer')->name('offers.update');
        Route::delete('offers/{seller_offer}', [SellerOfferController::class, 'destroy'])->whereNumber('seller_offer')->name('offers.destroy');

        Route::get('orders', [SellerOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{seller_order}', [SellerOrderController::class, 'show'])->whereNumber('seller_order')->name('orders.show');
        Route::patch('orders/{seller_order}/status', [SellerOrderController::class, 'updateStatus'])->whereNumber('seller_order')->name('orders.status');

        Route::get('commissions', [SellerCommissionController::class, 'index'])->name('commissions.index');
        Route::get('commissions/{commission}', [SellerCommissionController::class, 'show'])->whereNumber('commission')->name('commissions.show');

        Route::get('payouts', [SellerPayoutController::class, 'index'])->name('payouts.index');
        Route::post('payouts', [SellerPayoutController::class, 'store'])->name('payouts.store');
        Route::get('payouts/{payout}', [SellerPayoutController::class, 'show'])->whereNumber('payout')->name('payouts.show');
    });
});
