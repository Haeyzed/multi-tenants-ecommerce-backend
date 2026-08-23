<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Content\PublicContentController;
use App\Http\Controllers\Tenant\HR\PublicJobOfferController;
use App\Http\Controllers\Tenant\HR\PublicJobOpeningController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->name('tenant.public.')->group(function (): void {
    Route::get('pages/{slug}', [PublicContentController::class, 'showPage'])->name('pages.show');
    Route::get('blog/posts', [PublicContentController::class, 'indexPosts'])->name('blog.posts.index');
    Route::get('blog/posts/{slug}', [PublicContentController::class, 'showPost'])->name('blog.posts.show');
    Route::get('blog/categories', [PublicContentController::class, 'indexCategories'])->name('blog.categories.index');

    Route::middleware('hr.recruitment.public:listings')->group(function (): void {
        Route::get('jobs', [PublicJobOpeningController::class, 'index'])->name('jobs.index');
        Route::get('jobs/{slug}', [PublicJobOpeningController::class, 'show'])->name('jobs.show');
    });

    Route::post('jobs/{slug}/applications', [PublicJobOpeningController::class, 'apply'])
        ->middleware(['hr.recruitment.public:apply', 'throttle:10,1'])
        ->name('jobs.apply');

    Route::middleware('hr.recruitment.public:offers')->group(function (): void {
        Route::get('offers/{token}', [PublicJobOfferController::class, 'show'])->middleware('throttle:20,1')->name('offers.show');
        Route::post('offers/{token}/accept', [PublicJobOfferController::class, 'accept'])->middleware('throttle:10,1')->name('offers.accept');
        Route::post('offers/{token}/reject', [PublicJobOfferController::class, 'reject'])->middleware('throttle:10,1')->name('offers.reject');
    });
});
