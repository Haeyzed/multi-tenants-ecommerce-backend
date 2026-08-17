<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Storefront\StorefrontBrandController;
use App\Http\Controllers\Tenant\Storefront\StorefrontCategoryController;
use App\Http\Controllers\Tenant\Storefront\StorefrontCollectionController;
use App\Http\Controllers\Tenant\Storefront\StorefrontProductController;
use App\Http\Controllers\Tenant\Storefront\StorefrontRecommendationController;
use App\Http\Controllers\Tenant\Storefront\StorefrontReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('storefront')->name('storefront.')->group(function (): void {
    Route::get('products', [StorefrontProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [StorefrontProductController::class, 'show'])->name('products.show');
    Route::get('products/{product}/reviews', [StorefrontReviewController::class, 'index'])->whereNumber('product')->name('products.reviews.index');
    Route::get('products/{product}/recommendations', [StorefrontRecommendationController::class, 'index'])->whereNumber('product')->name('products.recommendations.index');
    Route::get('collections', [StorefrontCollectionController::class, 'index'])->name('collections.index');
    Route::get('collections/{collection}', [StorefrontCollectionController::class, 'show'])->name('collections.show');
    Route::get('brands', [StorefrontBrandController::class, 'index'])->name('brands.index');
    Route::get('brands/{brand}', [StorefrontBrandController::class, 'show'])->name('brands.show');
    Route::get('categories', [StorefrontCategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/tree', [StorefrontCategoryController::class, 'tree'])->name('categories.tree');
    Route::get('categories/{category}', [StorefrontCategoryController::class, 'show'])->name('categories.show');
});
