<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Analytics\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('analytics')->middleware('feature:advanced-reports')->name('tenant.analytics.')->group(function (): void {
    Route::get('overview', [AnalyticsController::class, 'overview'])->middleware('permission:analytics.view')->name('overview');
    Route::get('sales', [AnalyticsController::class, 'sales'])->middleware('permission:analytics.sales')->name('sales');
    Route::get('sales/breakdown', [AnalyticsController::class, 'salesBreakdown'])->middleware('permission:analytics.sales')->name('sales.breakdown');
    Route::get('customers', [AnalyticsController::class, 'customers'])->middleware('permission:analytics.customers')->name('customers');
    Route::get('products', [AnalyticsController::class, 'products'])->middleware('permission:analytics.products')->name('products');
    Route::get('inventory', [AnalyticsController::class, 'inventory'])->middleware('permission:analytics.inventory')->name('inventory');
    Route::get('marketplace', [AnalyticsController::class, 'marketplace'])->middleware('permission:analytics.marketplace')->name('marketplace');
    Route::get('coupons', [AnalyticsController::class, 'coupons'])->middleware('permission:analytics.sales')->name('coupons');
    Route::get('payments', [AnalyticsController::class, 'payments'])->middleware('permission:analytics.sales')->name('payments');
});
