<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Commerce\CartController;
use App\Http\Controllers\Tenant\Commerce\CheckoutController;
use App\Http\Controllers\Tenant\Commerce\CustomerDeliveryController;
use App\Http\Controllers\Tenant\Commerce\CustomerGiftCardController;
use App\Http\Controllers\Tenant\Commerce\CustomerInvoiceController;
use App\Http\Controllers\Tenant\Commerce\CustomerOrderController;
use App\Http\Controllers\Tenant\Commerce\CustomerOrderReturnController;
use App\Http\Controllers\Tenant\Commerce\CustomerShipmentController;
use App\Http\Controllers\Tenant\Commerce\CustomerStoreCreditController;
use App\Http\Controllers\Tenant\Commerce\CustomerWishlistController;
use App\Http\Controllers\Tenant\Commerce\PaymentController;
use App\Http\Controllers\Tenant\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Tenant\Customer\ProductReviewController as CustomerProductReviewController;
use App\Http\Controllers\Tenant\Customer\RecentlyViewedProductController;
use App\Http\Controllers\Tenant\Loyalty\CustomerLoyaltyController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->middleware('customer.guard')->name('customer.')->group(function (): void {
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('register', [CustomerAuthController::class, 'register'])->name('register');
        Route::post('login', [CustomerAuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [CustomerAuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [CustomerAuthController::class, 'resetPassword'])->name('reset-password');
    });

    Route::middleware('auth:customer')->group(function (): void {
        Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');
        Route::get('me', [CustomerAuthController::class, 'me'])->name('me');
        Route::match(['put', 'patch'], 'profile', [CustomerAuthController::class, 'updateProfile'])->name('profile');
        Route::post('avatar', [CustomerAuthController::class, 'storeAvatar'])->name('avatar.store');
        Route::delete('avatar', [CustomerAuthController::class, 'destroyAvatar'])->name('avatar.destroy');
        Route::post('change-password', [CustomerAuthController::class, 'changePassword'])->name('change-password');
        Route::post('email/verify', [CustomerAuthController::class, 'verifyEmail'])->name('email.verify');
        Route::middleware('throttle:6,1')->post('email/resend', [CustomerAuthController::class, 'resendVerification'])->name('email.resend');
        Route::delete('account', [CustomerAuthController::class, 'destroyAccount'])->name('account.destroy');

        Route::get('addresses', [CustomerAuthController::class, 'indexAddresses'])->name('addresses.index');
        Route::post('addresses', [CustomerAuthController::class, 'storeAddress'])->name('addresses.store');
        Route::match(['put', 'patch'], 'addresses/{address}', [CustomerAuthController::class, 'updateAddress'])->whereNumber('address')->name('addresses.update');
        Route::delete('addresses/{address}', [CustomerAuthController::class, 'destroyAddress'])->whereNumber('address')->name('addresses.destroy');
        Route::post('addresses/{address}/default', [CustomerAuthController::class, 'makeDefaultAddress'])->whereNumber('address')->name('addresses.default');

        Route::post('products/{product}/reviews', [CustomerProductReviewController::class, 'store'])->whereNumber('product')->name('products.reviews.store');
        Route::get('products/recently-viewed', [RecentlyViewedProductController::class, 'index'])->name('products.recently-viewed');

        Route::get('orders', [CustomerOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [CustomerOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::post('orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->whereNumber('order')->name('orders.cancel');
        Route::get('orders/{order}/refunds', [CustomerOrderController::class, 'refunds'])->whereNumber('order')->name('orders.refunds');
        Route::get('orders/{order}/shipments', [CustomerShipmentController::class, 'index'])->whereNumber('order')->name('orders.shipments.index');
        Route::get('orders/{order}/deliveries', [CustomerDeliveryController::class, 'index'])->whereNumber('order')->name('orders.deliveries.index');
        Route::get('orders/{order}/invoice', [CustomerInvoiceController::class, 'forOrder'])->whereNumber('order')->name('orders.invoice');
        Route::post('orders/{order}/returns', [CustomerOrderReturnController::class, 'store'])->whereNumber('order')->name('orders.returns.store');
        Route::get('returns', [CustomerOrderReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/{order_return}', [CustomerOrderReturnController::class, 'show'])->whereNumber('order_return')->name('returns.show');
        Route::get('invoices/{invoice}', [CustomerInvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
        Route::get('invoices/{invoice}/download', [CustomerInvoiceController::class, 'download'])->whereNumber('invoice')->name('invoices.download');
    });
});

Route::middleware(['customer.guard', 'auth:customer'])->group(function (): void {
    Route::get('wishlist', [CustomerWishlistController::class, 'show'])->name('customer.wishlist.show');
    Route::post('wishlist/items', [CustomerWishlistController::class, 'storeItem'])->name('customer.wishlist.items.store');
    Route::delete('wishlist/items/{item}', [CustomerWishlistController::class, 'destroyItem'])->whereNumber('item')->name('customer.wishlist.items.destroy');
    Route::get('wishlist/check/{product}', [CustomerWishlistController::class, 'check'])->whereNumber('product')->name('customer.wishlist.check');

    Route::get('cart', [CartController::class, 'show'])->name('customer.cart.show');
    Route::delete('cart', [CartController::class, 'destroy'])->name('customer.cart.destroy');
    Route::post('cart/items', [CartController::class, 'storeItem'])->name('customer.cart.items.store');
    Route::patch('cart/items/{item}', [CartController::class, 'updateItem'])->whereNumber('item')->name('customer.cart.items.update');
    Route::delete('cart/items/{item}', [CartController::class, 'destroyItem'])->whereNumber('item')->name('customer.cart.items.destroy');
    Route::post('cart/coupon', [CartController::class, 'applyCoupon'])->name('customer.cart.coupon.apply');
    Route::middleware('feature:loyalty')->group(function (): void {
        Route::get('loyalty/account', [CustomerLoyaltyController::class, 'account'])->name('customer.loyalty.account');
        Route::get('loyalty/transactions', [CustomerLoyaltyController::class, 'transactions'])->name('customer.loyalty.transactions');
        Route::post('loyalty/redemption-preview', [CustomerLoyaltyController::class, 'previewRedemption'])->name('customer.loyalty.redemption-preview');
    });

    Route::middleware('feature:gift-cards')->group(function (): void {
        Route::post('cart/gift-card/preview', [CustomerGiftCardController::class, 'preview'])->name('customer.cart.gift-card.preview');
    });

    Route::middleware('feature:store-credit')->group(function (): void {
        Route::get('store-credit', [CustomerStoreCreditController::class, 'show'])->name('customer.store-credit.show');
        Route::get('store-credit/transactions', [CustomerStoreCreditController::class, 'transactions'])->name('customer.store-credit.transactions');
    });

    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('customer.checkout.store');
    Route::post('checkout/pay', [PaymentController::class, 'pay'])->middleware('throttle:20,1')->name('customer.checkout.pay');
    Route::post('payments/verify', [PaymentController::class, 'verify'])->middleware('throttle:30,1')->name('customer.payments.verify');
});
