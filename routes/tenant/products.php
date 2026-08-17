<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Catalog\ProductBadgeController;
use App\Http\Controllers\Tenant\Catalog\ProductTagController;
use App\Http\Controllers\Tenant\Catalog\SeoController;
use App\Http\Controllers\Tenant\Inventory\InventoryController;
use App\Http\Controllers\Tenant\Product\ProductBundleController;
use App\Http\Controllers\Tenant\Product\ProductController;
use App\Http\Controllers\Tenant\Product\ProductRelationController;
use App\Http\Controllers\Tenant\Product\ProductReviewController;
use App\Http\Controllers\Tenant\Product\ProductSpecificationController;
use App\Http\Controllers\Tenant\Product\ProductVariantController;
use Illuminate\Support\Facades\Route;

Route::get('products/options', [ProductController::class, 'options'])->middleware('permission:products.view')->name('tenant.products.options');
Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('tenant.products.index');
Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('tenant.products.store');
Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.show')->whereNumber('product')->name('tenant.products.show');
Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.update');
Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->whereNumber('product')->name('tenant.products.destroy');
Route::post('products/{product}/images', [ProductController::class, 'storeImages'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.images.store');
Route::delete('products/{product}/images', [ProductController::class, 'destroyImages'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.images.destroy');
Route::get('products/{product}/seo', [SeoController::class, 'showProduct'])->middleware('permission:products.show')->whereNumber('product')->name('tenant.products.seo.show');
Route::match(['put', 'patch'], 'products/{product}/seo', [SeoController::class, 'upsertProduct'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.seo.upsert');
Route::put('products/{product}/tags', [ProductTagController::class, 'syncToProduct'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.tags.sync');
Route::put('products/{product}/badges', [ProductBadgeController::class, 'syncToProduct'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.badges.sync');
Route::get('products/{product}/relations/{type}', [ProductRelationController::class, 'show'])->middleware('permission:products.show')->whereNumber('product')->whereIn('type', ['related', 'upsell', 'cross_sell'])->name('tenant.products.relations.show');
Route::put('products/{product}/relations/{type}', [ProductRelationController::class, 'sync'])->middleware('permission:products.update')->whereNumber('product')->whereIn('type', ['related', 'upsell', 'cross_sell'])->name('tenant.products.relations.sync');
Route::get('products/{product}/specifications', [ProductSpecificationController::class, 'index'])->middleware('permission:products.show')->whereNumber('product')->name('tenant.products.specifications.index');
Route::put('products/{product}/specifications', [ProductSpecificationController::class, 'sync'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.specifications.sync');
Route::get('products/{product}/bundle-items', [ProductBundleController::class, 'index'])->middleware('permission:products.show')->whereNumber('product')->name('tenant.products.bundle-items.index');
Route::put('products/{product}/bundle-items', [ProductBundleController::class, 'sync'])->middleware('permission:products.update')->whereNumber('product')->name('tenant.products.bundle-items.sync');

Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])->middleware('permission:variants.view')->whereNumber('product')->name('tenant.products.variants.index');
Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->middleware('permission:variants.create')->whereNumber('product')->name('tenant.products.variants.store');
Route::get('products/{product}/variants/{variant}', [ProductVariantController::class, 'show'])->middleware('permission:variants.show')->whereNumber(['product', 'variant'])->name('tenant.products.variants.show');
Route::match(['put', 'patch'], 'products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.update');
Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->middleware('permission:variants.delete')->whereNumber(['product', 'variant'])->name('tenant.products.variants.destroy');
Route::post('products/{product}/variants/{variant}/image', [ProductVariantController::class, 'storeImage'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.image.store');
Route::delete('products/{product}/variants/{variant}/image', [ProductVariantController::class, 'destroyImage'])->middleware('permission:variants.update')->whereNumber(['product', 'variant'])->name('tenant.products.variants.image.destroy');

Route::get('reviews', [ProductReviewController::class, 'index'])->middleware('permission:reviews.view')->name('tenant.reviews.index');
Route::patch('reviews/{review}/status', [ProductReviewController::class, 'moderate'])->middleware('permission:reviews.moderate')->whereNumber('review')->name('tenant.reviews.status');
Route::delete('reviews/{review}', [ProductReviewController::class, 'destroy'])->middleware('permission:reviews.delete')->whereNumber('review')->name('tenant.reviews.destroy');

Route::get('inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('tenant.inventory.index');
Route::get('inventory/{inventory}', [InventoryController::class, 'show'])->middleware('permission:inventory.view')->whereNumber('inventory')->name('tenant.inventory.show');
Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.adjust');
Route::post('inventory/{inventory}/reserve', [InventoryController::class, 'reserve'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.reserve');
Route::post('inventory/{inventory}/release', [InventoryController::class, 'release'])->middleware('permission:inventory.adjust')->whereNumber('inventory')->name('tenant.inventory.release');
Route::post('inventory/{inventory}/transfer', [InventoryController::class, 'transfer'])->middleware('permission:inventory.transfer')->whereNumber('inventory')->name('tenant.inventory.transfer');
