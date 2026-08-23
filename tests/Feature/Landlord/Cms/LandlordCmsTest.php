<?php

declare(strict_types=1);

use App\Enums\Content\ContentStatus;
use App\Models\Landlord\Content\BlogCategory;
use App\Models\Landlord\Content\BlogPost;
use App\Models\Landlord\Content\Page;
use App\Services\Landlord\Content\BlogCategoryService;
use App\Services\Landlord\Content\BlogPostService;
use App\Services\Landlord\Content\PageService;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);
});

test('landlord draft content is not publicly visible', function (): void {
    $pageService = app(PageService::class);
    $postService = app(BlogPostService::class);

    $page = $pageService->store([
        'title' => 'Draft About',
        'content' => 'Secret',
        'status' => ContentStatus::Draft->value,
    ]);

    $post = $postService->store([
        'title' => 'Draft Post',
        'content' => 'Hidden',
        'status' => ContentStatus::Draft->value,
    ]);

    expect(fn () => $pageService->showPublicBySlug($page->slug))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => $postService->showPublicBySlug($post->slug))
        ->toThrow(ModelNotFoundException::class)
        ->and($postService->listPublic()->total())->toBe(0);
});

test('landlord published content is publicly visible', function (): void {
    $pageService = app(PageService::class);
    $postService = app(BlogPostService::class);
    $categoryService = app(BlogCategoryService::class);

    $category = $categoryService->store(['name' => 'News']);

    $page = $pageService->store([
        'title' => 'About Us',
        'content' => 'Public page',
        'status' => ContentStatus::Published->value,
        'published_at' => now()->subMinute()->toDateTimeString(),
        'seo' => ['meta_title' => 'About'],
    ]);

    $post = $postService->store([
        'title' => 'Launch Day',
        'excerpt' => 'We launched',
        'content' => 'Public post',
        'status' => ContentStatus::Published->value,
        'published_at' => now()->subMinute()->toDateTimeString(),
        'blog_category_id' => $category->id,
    ]);

    expect($pageService->showPublicBySlug($page->slug)->title)->toBe('About Us')
        ->and($postService->showPublicBySlug($post->slug)->title)->toBe('Launch Day')
        ->and($postService->listPublic()->total())->toBe(1)
        ->and($page->fresh('seo')->seo?->meta_title)->toBe('About');
});

test('landlord cms slugs are unique', function (): void {
    Page::factory()->create(['slug' => 'unique-page']);
    BlogPost::factory()->create(['slug' => 'unique-post']);
    BlogCategory::factory()->create(['slug' => 'unique-category']);

    expect(fn () => DB::table('landlord_pages')->insert([
        'title' => 'Dup',
        'slug' => 'unique-page',
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(Exception::class)
        ->and(fn () => DB::table('landlord_blog_posts')->insert([
            'title' => 'Dup',
            'slug' => 'unique-post',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(Exception::class)
        ->and(fn () => DB::table('landlord_blog_categories')->insert([
            'name' => 'Dup',
            'slug' => 'unique-category',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(Exception::class);
});
