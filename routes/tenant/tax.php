<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Tax\TaxController;
use Illuminate\Support\Facades\Route;

Route::get('taxes', [TaxController::class, 'index'])->middleware('permission:taxes.view')->name('tenant.taxes.index');
Route::post('taxes', [TaxController::class, 'store'])->middleware('permission:taxes.create')->name('tenant.taxes.store');
Route::get('taxes/{tax}', [TaxController::class, 'show'])->middleware('permission:taxes.view')->whereNumber('tax')->name('tenant.taxes.show');
Route::match(['put', 'patch'], 'taxes/{tax}', [TaxController::class, 'update'])->middleware('permission:taxes.update')->whereNumber('tax')->name('tenant.taxes.update');
Route::delete('taxes/{tax}', [TaxController::class, 'destroy'])->middleware('permission:taxes.delete')->whereNumber('tax')->name('tenant.taxes.destroy');
Route::get('tax-zones', [TaxController::class, 'indexZones'])->middleware('permission:taxes.view')->name('tenant.tax-zones.index');
Route::post('tax-zones', [TaxController::class, 'storeZone'])->middleware('permission:taxes.create')->name('tenant.tax-zones.store');
Route::get('tax-zones/{tax_zone}', [TaxController::class, 'showZone'])->middleware('permission:taxes.view')->whereNumber('tax_zone')->name('tenant.tax-zones.show');
Route::match(['put', 'patch'], 'tax-zones/{tax_zone}', [TaxController::class, 'updateZone'])->middleware('permission:taxes.update')->whereNumber('tax_zone')->name('tenant.tax-zones.update');
Route::delete('tax-zones/{tax_zone}', [TaxController::class, 'destroyZone'])->middleware('permission:taxes.delete')->whereNumber('tax_zone')->name('tenant.tax-zones.destroy');
Route::post('tax-rules', [TaxController::class, 'storeRule'])->middleware('permission:taxes.create')->name('tenant.tax-rules.store');
Route::delete('tax-rules/{tax_rule}', [TaxController::class, 'destroyRule'])->middleware('permission:taxes.delete')->whereNumber('tax_rule')->name('tenant.tax-rules.destroy');
