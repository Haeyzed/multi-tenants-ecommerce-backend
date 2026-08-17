<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Cms\PublicCmsController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->name('tenant.public.')->group(function (): void {
    Route::get('pages/{slug}', [PublicCmsController::class, 'showPage'])->name('pages.show');
    Route::get('blog/posts', [PublicCmsController::class, 'indexPosts'])->name('blog.posts.index');
    Route::get('blog/posts/{slug}', [PublicCmsController::class, 'showPost'])->name('blog.posts.show');
    Route::get('blog/categories', [PublicCmsController::class, 'indexCategories'])->name('blog.categories.index');
});
