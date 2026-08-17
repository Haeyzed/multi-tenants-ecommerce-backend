<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Brand\BrandController;
use App\Http\Controllers\Tenant\Catalog\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('brands/options', [BrandController::class, 'options'])->middleware('permission:brands.view')->name('tenant.brands.options');
Route::get('brands', [BrandController::class, 'index'])->middleware('permission:brands.view')->name('tenant.brands.index');
Route::post('brands', [BrandController::class, 'store'])->middleware('permission:brands.create')->name('tenant.brands.store');
Route::get('brands/{brand}', [BrandController::class, 'show'])->middleware('permission:brands.show')->whereNumber('brand')->name('tenant.brands.show');
Route::match(['put', 'patch'], 'brands/{brand}', [BrandController::class, 'update'])->middleware('permission:brands.update')->whereNumber('brand')->name('tenant.brands.update');
Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:brands.delete')->whereNumber('brand')->name('tenant.brands.destroy');
Route::post('brands/{brand}/logo', [BrandController::class, 'storeLogo'])->middleware('permission:brands.update')->whereNumber('brand')->name('tenant.brands.logo.store');
Route::delete('brands/{brand}/logo', [BrandController::class, 'destroyLogo'])->middleware('permission:brands.update')->whereNumber('brand')->name('tenant.brands.logo.destroy');
Route::get('brands/{brand}/seo', [SeoController::class, 'showBrand'])->middleware('permission:brands.show')->whereNumber('brand')->name('tenant.brands.seo.show');
Route::match(['put', 'patch'], 'brands/{brand}/seo', [SeoController::class, 'upsertBrand'])->middleware('permission:brands.update')->whereNumber('brand')->name('tenant.brands.seo.upsert');
