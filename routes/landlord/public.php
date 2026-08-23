<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Content\PublicContentController;
use App\Http\Controllers\Public\Plan\PlanController as PublicPlanController;
use App\Http\Controllers\Public\Tenant\PublicTenantController;
use App\Http\Controllers\Public\TenantProfile\TenantProfileController as PublicTenantProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->middleware('central')->name('public.')->group(function (): void {
    Route::get('plans', [PublicPlanController::class, 'index'])->name('plans.index');
    Route::get('tenant', [PublicTenantController::class, 'show'])->name('tenant.show');
    Route::get('stores/{slug}', [PublicTenantProfileController::class, 'show'])->name('stores.show');
    Route::get('pages/{slug}', [PublicContentController::class, 'showPage'])->name('pages.show');
    Route::get('blog/posts', [PublicContentController::class, 'indexPosts'])->name('blog.posts.index');
});
