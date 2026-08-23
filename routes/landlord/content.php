<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\Cms\BlogCategoryController as LandlordBlogCategoryController;
use App\Http\Controllers\Landlord\Cms\BlogPostController as LandlordBlogPostController;
use App\Http\Controllers\Landlord\Cms\PageController as LandlordPageController;
use Illuminate\Support\Facades\Route;

Route::get('blog-categories/options', [LandlordBlogCategoryController::class, 'options'])->middleware('permission:cms.view|cms.manage')->name('landlord.blog-categories.options');
Route::get('blog-categories', [LandlordBlogCategoryController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('landlord.blog-categories.index');
Route::post('blog-categories', [LandlordBlogCategoryController::class, 'store'])->middleware('permission:cms.manage')->name('landlord.blog-categories.store');
Route::get('blog-categories/{blog_category}', [LandlordBlogCategoryController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('blog_category')->name('landlord.blog-categories.show');
Route::match(['put', 'patch'], 'blog-categories/{blog_category}', [LandlordBlogCategoryController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('blog_category')->name('landlord.blog-categories.update');
Route::delete('blog-categories/{blog_category}', [LandlordBlogCategoryController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('blog_category')->name('landlord.blog-categories.destroy');

Route::get('blog-posts', [LandlordBlogPostController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('landlord.blog-posts.index');
Route::post('blog-posts', [LandlordBlogPostController::class, 'store'])->middleware('permission:cms.manage')->name('landlord.blog-posts.store');
Route::get('blog-posts/{blog_post}', [LandlordBlogPostController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.show');
Route::match(['put', 'patch'], 'blog-posts/{blog_post}', [LandlordBlogPostController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.update');
Route::delete('blog-posts/{blog_post}', [LandlordBlogPostController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.destroy');
Route::post('blog-posts/{blog_post}/featured-image', [LandlordBlogPostController::class, 'storeFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.featured-image.store');
Route::delete('blog-posts/{blog_post}/featured-image', [LandlordBlogPostController::class, 'destroyFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('landlord.blog-posts.featured-image.destroy');

Route::get('pages', [LandlordPageController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('landlord.pages.index');
Route::post('pages', [LandlordPageController::class, 'store'])->middleware('permission:cms.manage')->name('landlord.pages.store');
Route::get('pages/{page}', [LandlordPageController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('page')->name('landlord.pages.show');
Route::match(['put', 'patch'], 'pages/{page}', [LandlordPageController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('page')->name('landlord.pages.update');
Route::delete('pages/{page}', [LandlordPageController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('page')->name('landlord.pages.destroy');
Route::post('pages/{page}/featured-image', [LandlordPageController::class, 'storeFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('page')->name('landlord.pages.featured-image.store');
Route::delete('pages/{page}/featured-image', [LandlordPageController::class, 'destroyFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('page')->name('landlord.pages.featured-image.destroy');
