<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Cms\BlogCategoryController;
use App\Http\Controllers\Tenant\Cms\BlogPostController;
use App\Http\Controllers\Tenant\Cms\PageController as CmsPageController;
use Illuminate\Support\Facades\Route;

Route::get('blog-categories/options', [BlogCategoryController::class, 'options'])->middleware('permission:cms.view|cms.manage')->name('tenant.blog-categories.options');
Route::get('blog-categories', [BlogCategoryController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('tenant.blog-categories.index');
Route::post('blog-categories', [BlogCategoryController::class, 'store'])->middleware('permission:cms.manage')->name('tenant.blog-categories.store');
Route::get('blog-categories/{blog_category}', [BlogCategoryController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('blog_category')->name('tenant.blog-categories.show');
Route::match(['put', 'patch'], 'blog-categories/{blog_category}', [BlogCategoryController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('blog_category')->name('tenant.blog-categories.update');
Route::delete('blog-categories/{blog_category}', [BlogCategoryController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('blog_category')->name('tenant.blog-categories.destroy');

Route::get('blog-posts', [BlogPostController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('tenant.blog-posts.index');
Route::post('blog-posts', [BlogPostController::class, 'store'])->middleware('permission:cms.manage')->name('tenant.blog-posts.store');
Route::get('blog-posts/{blog_post}', [BlogPostController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('blog_post')->name('tenant.blog-posts.show');
Route::match(['put', 'patch'], 'blog-posts/{blog_post}', [BlogPostController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('tenant.blog-posts.update');
Route::delete('blog-posts/{blog_post}', [BlogPostController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('tenant.blog-posts.destroy');
Route::post('blog-posts/{blog_post}/featured-image', [BlogPostController::class, 'storeFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('tenant.blog-posts.featured-image.store');
Route::delete('blog-posts/{blog_post}/featured-image', [BlogPostController::class, 'destroyFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('blog_post')->name('tenant.blog-posts.featured-image.destroy');

Route::get('pages', [CmsPageController::class, 'index'])->middleware('permission:cms.view|cms.manage')->name('tenant.pages.index');
Route::post('pages', [CmsPageController::class, 'store'])->middleware('permission:cms.manage')->name('tenant.pages.store');
Route::get('pages/{page}', [CmsPageController::class, 'show'])->middleware('permission:cms.view|cms.manage')->whereNumber('page')->name('tenant.pages.show');
Route::match(['put', 'patch'], 'pages/{page}', [CmsPageController::class, 'update'])->middleware('permission:cms.manage')->whereNumber('page')->name('tenant.pages.update');
Route::delete('pages/{page}', [CmsPageController::class, 'destroy'])->middleware('permission:cms.manage')->whereNumber('page')->name('tenant.pages.destroy');
Route::post('pages/{page}/featured-image', [CmsPageController::class, 'storeFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('page')->name('tenant.pages.featured-image.store');
Route::delete('pages/{page}/featured-image', [CmsPageController::class, 'destroyFeaturedImage'])->middleware('permission:cms.manage')->whereNumber('page')->name('tenant.pages.featured-image.destroy');
