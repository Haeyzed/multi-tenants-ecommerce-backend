<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Commerce\CouponController;
use App\Http\Controllers\Tenant\Commerce\FlashSaleController;
use App\Http\Controllers\Tenant\Commerce\GiftCardController;
use App\Http\Controllers\Tenant\Commerce\InvoiceController;
use App\Http\Controllers\Tenant\Commerce\PromotionController;
use App\Http\Controllers\Tenant\Commerce\RefundController;
use App\Http\Controllers\Tenant\Commerce\StoreCreditController;
use App\Http\Controllers\Tenant\Loyalty\LoyaltyAccountController;
use App\Http\Controllers\Tenant\Loyalty\LoyaltyProgramController;
use App\Http\Controllers\Tenant\Payment\PaymentGatewayController;
use Illuminate\Support\Facades\Route;

Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:coupons.view')->name('tenant.coupons.index');
Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:coupons.create')->name('tenant.coupons.store');
Route::get('coupons/{coupon}', [CouponController::class, 'show'])->middleware('permission:coupons.view')->whereNumber('coupon')->name('tenant.coupons.show');
Route::match(['put', 'patch'], 'coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupons.update')->whereNumber('coupon')->name('tenant.coupons.update');
Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupons.delete')->whereNumber('coupon')->name('tenant.coupons.destroy');

Route::get('promotions', [PromotionController::class, 'index'])->middleware('permission:promotions.view')->name('tenant.promotions.index');
Route::post('promotions', [PromotionController::class, 'store'])->middleware('permission:promotions.create')->name('tenant.promotions.store');
Route::get('promotions/{promotion}', [PromotionController::class, 'show'])->middleware('permission:promotions.view')->whereNumber('promotion')->name('tenant.promotions.show');
Route::match(['put', 'patch'], 'promotions/{promotion}', [PromotionController::class, 'update'])->middleware('permission:promotions.update')->whereNumber('promotion')->name('tenant.promotions.update');
Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->middleware('permission:promotions.delete')->whereNumber('promotion')->name('tenant.promotions.destroy');

Route::get('flash-sales', [FlashSaleController::class, 'index'])->middleware('permission:flash_sales.view')->name('tenant.flash-sales.index');
Route::post('flash-sales', [FlashSaleController::class, 'store'])->middleware('permission:flash_sales.create')->name('tenant.flash-sales.store');
Route::get('flash-sales/{flashSale}', [FlashSaleController::class, 'show'])->middleware('permission:flash_sales.view')->whereNumber('flashSale')->name('tenant.flash-sales.show');
Route::match(['put', 'patch'], 'flash-sales/{flashSale}', [FlashSaleController::class, 'update'])->middleware('permission:flash_sales.update')->whereNumber('flashSale')->name('tenant.flash-sales.update');
Route::delete('flash-sales/{flashSale}', [FlashSaleController::class, 'destroy'])->middleware('permission:flash_sales.delete')->whereNumber('flashSale')->name('tenant.flash-sales.destroy');
Route::post('flash-sales/{flashSale}/items', [FlashSaleController::class, 'storeItem'])->middleware('permission:flash_sales.update')->whereNumber('flashSale')->name('tenant.flash-sales.items.store');
Route::match(['put', 'patch'], 'flash-sales/{flashSale}/items/{flashSaleItem}', [FlashSaleController::class, 'updateItem'])->middleware('permission:flash_sales.update')->whereNumber('flashSale')->whereNumber('flashSaleItem')->name('tenant.flash-sales.items.update');
Route::delete('flash-sales/{flashSale}/items/{flashSaleItem}', [FlashSaleController::class, 'destroyItem'])->middleware('permission:flash_sales.update')->whereNumber('flashSale')->whereNumber('flashSaleItem')->name('tenant.flash-sales.items.destroy');

Route::middleware('feature:loyalty')->group(function (): void {
    Route::get('loyalty/program', [LoyaltyProgramController::class, 'show'])->middleware('permission:loyalty.view')->name('tenant.loyalty.program.show');
    Route::match(['put', 'patch'], 'loyalty/program', [LoyaltyProgramController::class, 'update'])->middleware('permission:loyalty.manage')->name('tenant.loyalty.program.update');
    Route::get('loyalty/accounts', [LoyaltyAccountController::class, 'index'])->middleware('permission:loyalty.view')->name('tenant.loyalty.accounts.index');
    Route::get('loyalty/accounts/{loyalty_account}/transactions', [LoyaltyAccountController::class, 'transactions'])->middleware('permission:loyalty.view')->whereNumber('loyalty_account')->name('tenant.loyalty.accounts.transactions');
    Route::post('loyalty/accounts/{loyalty_account}/adjustments', [LoyaltyAccountController::class, 'storeAdjustment'])->middleware('permission:loyalty.manage')->whereNumber('loyalty_account')->name('tenant.loyalty.accounts.adjustments.store');
});

Route::middleware('feature:gift-cards')->group(function (): void {
    Route::get('gift-cards', [GiftCardController::class, 'index'])->middleware('permission:gift_cards.view')->name('tenant.gift-cards.index');
    Route::post('gift-cards', [GiftCardController::class, 'store'])->middleware('permission:gift_cards.create')->name('tenant.gift-cards.store');
    Route::get('gift-cards/{gift_card}', [GiftCardController::class, 'show'])->middleware('permission:gift_cards.view')->whereNumber('gift_card')->name('tenant.gift-cards.show');
    Route::match(['put', 'patch'], 'gift-cards/{gift_card}', [GiftCardController::class, 'update'])->middleware('permission:gift_cards.update')->whereNumber('gift_card')->name('tenant.gift-cards.update');
    Route::post('gift-cards/{gift_card}/activate', [GiftCardController::class, 'activate'])->middleware('permission:gift_cards.update')->whereNumber('gift_card')->name('tenant.gift-cards.activate');
    Route::post('gift-cards/{gift_card}/cancel', [GiftCardController::class, 'cancel'])->middleware('permission:gift_cards.cancel')->whereNumber('gift_card')->name('tenant.gift-cards.cancel');
});

Route::middleware('feature:store-credit')->group(function (): void {
    Route::get('store-credit/accounts', [StoreCreditController::class, 'index'])->middleware('permission:store_credit.view')->name('tenant.store-credit.accounts.index');
    Route::get('store-credit/customers/{customer}', [StoreCreditController::class, 'show'])->middleware('permission:store_credit.view')->whereNumber('customer')->name('tenant.store-credit.customers.show');
    Route::get('store-credit/customers/{customer}/transactions', [StoreCreditController::class, 'transactions'])->middleware('permission:store_credit.view')->whereNumber('customer')->name('tenant.store-credit.customers.transactions');
    Route::post('store-credit/customers/{customer}/credit', [StoreCreditController::class, 'credit'])->middleware('permission:store_credit.manage')->whereNumber('customer')->name('tenant.store-credit.customers.credit');
    Route::post('store-credit/customers/{customer}/debit', [StoreCreditController::class, 'debit'])->middleware('permission:store_credit.manage')->whereNumber('customer')->name('tenant.store-credit.customers.debit');
});

Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:invoices.view')->name('tenant.invoices.index');
Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices.view')->whereNumber('invoice')->name('tenant.invoices.show');
Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->middleware('permission:invoices.download')->whereNumber('invoice')->name('tenant.invoices.download');

Route::get('refunds', [RefundController::class, 'index'])->middleware('permission:refunds.view')->name('tenant.refunds.index');
Route::post('orders/{order}/refunds', [RefundController::class, 'store'])->middleware('permission:refunds.create')->whereNumber('order')->name('tenant.orders.refunds.store');
Route::get('refunds/{refund}', [RefundController::class, 'show'])->middleware('permission:refunds.view')->whereNumber('refund')->name('tenant.refunds.show');

Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])
    ->middleware('permission:payments.manage|payment_gateways.view|payment_gateways.manage')
    ->name('tenant.payment-gateways.index');
Route::put('payment-gateways', [PaymentGatewayController::class, 'upsert'])
    ->middleware('permission:payments.manage|payment_gateways.manage')
    ->name('tenant.payment-gateways.upsert');
Route::post('payment-gateways/{gateway}/enable', [PaymentGatewayController::class, 'enable'])
    ->middleware('permission:payments.manage|payment_gateways.manage')
    ->name('tenant.payment-gateways.enable');
Route::post('payment-gateways/{gateway}/disable', [PaymentGatewayController::class, 'disable'])
    ->middleware('permission:payments.manage|payment_gateways.manage')
    ->name('tenant.payment-gateways.disable');
