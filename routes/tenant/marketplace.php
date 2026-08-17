<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Marketplace\SellerCommissionController;
use App\Http\Controllers\Tenant\Marketplace\SellerController;
use App\Http\Controllers\Tenant\Marketplace\SellerGroupController;
use App\Http\Controllers\Tenant\Marketplace\SellerOfferController;
use App\Http\Controllers\Tenant\Marketplace\SellerOrderController;
use App\Http\Controllers\Tenant\Marketplace\SellerPayoutController;
use Illuminate\Support\Facades\Route;

Route::middleware('marketplace.enabled')->group(function (): void {
    Route::get('seller-groups/options', [SellerGroupController::class, 'options'])->middleware('permission:seller_groups.view')->name('tenant.seller-groups.options');
    Route::get('seller-groups', [SellerGroupController::class, 'index'])->middleware('permission:seller_groups.view')->name('tenant.seller-groups.index');
    Route::post('seller-groups', [SellerGroupController::class, 'store'])->middleware('permission:seller_groups.create')->name('tenant.seller-groups.store');
    Route::get('seller-groups/{seller_group}', [SellerGroupController::class, 'show'])->middleware('permission:seller_groups.show')->whereNumber('seller_group')->name('tenant.seller-groups.show');
    Route::match(['put', 'patch'], 'seller-groups/{seller_group}', [SellerGroupController::class, 'update'])->middleware('permission:seller_groups.update')->whereNumber('seller_group')->name('tenant.seller-groups.update');
    Route::delete('seller-groups/{seller_group}', [SellerGroupController::class, 'destroy'])->middleware('permission:seller_groups.delete')->whereNumber('seller_group')->name('tenant.seller-groups.destroy');

    Route::get('sellers', [SellerController::class, 'index'])->middleware('permission:sellers.view')->name('tenant.sellers.index');
    Route::post('sellers', [SellerController::class, 'store'])->middleware('permission:sellers.create')->name('tenant.sellers.store');
    Route::get('sellers/{seller}', [SellerController::class, 'show'])->middleware('permission:sellers.view')->whereNumber('seller')->name('tenant.sellers.show');
    Route::match(['put', 'patch'], 'sellers/{seller}', [SellerController::class, 'update'])->middleware('permission:sellers.update')->whereNumber('seller')->name('tenant.sellers.update');
    Route::patch('sellers/{seller}/approve', [SellerController::class, 'approve'])->middleware('permission:sellers.approve')->whereNumber('seller')->name('tenant.sellers.approve');
    Route::patch('sellers/{seller}/reject', [SellerController::class, 'reject'])->middleware('permission:sellers.reject')->whereNumber('seller')->name('tenant.sellers.reject');
    Route::patch('sellers/{seller}/suspend', [SellerController::class, 'suspend'])->middleware('permission:sellers.suspend')->whereNumber('seller')->name('tenant.sellers.suspend');
    Route::patch('sellers/{seller}/activate', [SellerController::class, 'activate'])->middleware('permission:sellers.update')->whereNumber('seller')->name('tenant.sellers.activate');

    Route::get('seller-offers', [SellerOfferController::class, 'index'])->middleware('permission:seller_offers.view')->name('tenant.seller-offers.index');
    Route::post('seller-offers', [SellerOfferController::class, 'store'])->middleware('permission:seller_offers.create')->name('tenant.seller-offers.store');
    Route::get('seller-offers/{seller_offer}', [SellerOfferController::class, 'show'])->middleware('permission:seller_offers.view')->whereNumber('seller_offer')->name('tenant.seller-offers.show');
    Route::match(['put', 'patch'], 'seller-offers/{seller_offer}', [SellerOfferController::class, 'update'])->middleware('permission:seller_offers.update')->whereNumber('seller_offer')->name('tenant.seller-offers.update');
    Route::delete('seller-offers/{seller_offer}', [SellerOfferController::class, 'destroy'])->middleware('permission:seller_offers.delete')->whereNumber('seller_offer')->name('tenant.seller-offers.destroy');

    Route::get('seller-orders', [SellerOrderController::class, 'index'])->middleware('permission:seller_orders.view')->name('tenant.seller-orders.index');
    Route::get('seller-orders/{seller_order}', [SellerOrderController::class, 'show'])->middleware('permission:seller_orders.view')->whereNumber('seller_order')->name('tenant.seller-orders.show');
    Route::patch('seller-orders/{seller_order}/status', [SellerOrderController::class, 'updateStatus'])->middleware('permission:seller_orders.manage')->whereNumber('seller_order')->name('tenant.seller-orders.status');

    Route::get('commissions', [SellerCommissionController::class, 'index'])->middleware('permission:commissions.view')->name('tenant.commissions.index');
    Route::get('commissions/{commission}', [SellerCommissionController::class, 'show'])->middleware('permission:commissions.view')->whereNumber('commission')->name('tenant.commissions.show');

    Route::get('payouts', [SellerPayoutController::class, 'index'])->middleware('permission:payouts.view')->name('tenant.payouts.index');
    Route::post('payouts', [SellerPayoutController::class, 'store'])->middleware('permission:payouts.manage')->name('tenant.payouts.store');
    Route::get('payouts/{payout}', [SellerPayoutController::class, 'show'])->middleware('permission:payouts.view')->whereNumber('payout')->name('tenant.payouts.show');
});
