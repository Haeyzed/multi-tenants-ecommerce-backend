<?php

declare(strict_types=1);

use App\Enums\Cms\CmsContentStatus;
use App\Models\Tenant\Cms\BlogCategory;
use App\Models\Tenant\Cms\BlogPost;
use App\Models\Tenant\Cms\Page;
use App\Models\Tenant\User;
use App\Services\Tenant\Cms\BlogCategoryService;
use App\Services\Tenant\Cms\BlogPostService;
use App\Services\Tenant\Cms\PageService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Selective tenant migrates: SQLite DDL is not rolled back with RefreshDatabase
    // transactions, so only run once per process when tables are missing.
    if (! Schema::hasTable('blog_categories')) {
        foreach ([
            '2026_08_15_050007_create_seo_meta_table.php',
            '2026_08_15_060001_create_commerce_settings_table.php',
            '2026_08_16_161047_create_blog_categories_table.php',
            '2026_08_16_161055_create_blog_posts_table.php',
            '2026_08_16_161108_create_cms_pages_table.php',
        ] as $file) {
            $this->artisan('migrate', [
                '--path' => database_path('migrations/tenant/'.$file),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);
});

test('tenant draft content is not publicly visible', function (): void {
    $pageService = app(PageService::class);
    $postService = app(BlogPostService::class);

    $page = $pageService->store([
        'title' => 'Draft FAQ',
        'status' => CmsContentStatus::Draft->value,
    ]);

    $post = $postService->store([
        'title' => 'Draft Article',
        'status' => CmsContentStatus::Draft->value,
    ]);

    expect(fn () => $pageService->showPublicBySlug($page->slug))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => $postService->showPublicBySlug($post->slug))
        ->toThrow(ModelNotFoundException::class)
        ->and($postService->listPublic()->total())->toBe(0);
});

test('tenant published content is publicly visible with morph seo', function (): void {
    $pageService = app(PageService::class);
    $postService = app(BlogPostService::class);
    $categoryService = app(BlogCategoryService::class);

    $category = $categoryService->store(['name' => 'Guides']);
    $author = User::factory()->create();

    $page = $pageService->store([
        'title' => 'Shipping Policy',
        'content' => 'Ships in 2 days',
        'status' => CmsContentStatus::Published->value,
        'published_at' => now()->subMinute()->toDateTimeString(),
        'seo' => ['meta_title' => 'Shipping'],
    ]);

    $post = $postService->store([
        'title' => 'How to Order',
        'excerpt' => 'A short guide',
        'content' => 'Step by step',
        'status' => CmsContentStatus::Published->value,
        'published_at' => now()->subMinute()->toDateTimeString(),
        'author_id' => $author->id,
        'blog_category_id' => $category->id,
        'seo' => ['meta_title' => 'Order Guide'],
    ]);

    expect($pageService->showPublicBySlug($page->slug)->title)->toBe('Shipping Policy')
        ->and($postService->showPublicBySlug($post->slug)->title)->toBe('How to Order')
        ->and($postService->listPublic()->total())->toBe(1)
        ->and($categoryService->listPublic())->toHaveCount(1)
        ->and($page->fresh('seo')->seo?->meta_title)->toBe('Shipping')
        ->and($post->fresh('seo')->seo?->meta_title)->toBe('Order Guide');
});

test('tenant cms slugs are unique', function (): void {
    Page::factory()->create(['slug' => 'tenant-page']);
    BlogPost::factory()->create(['slug' => 'tenant-post']);
    BlogCategory::factory()->create(['slug' => 'tenant-category']);

    expect(fn () => DB::table('pages')->insert([
        'title' => 'Dup',
        'slug' => 'tenant-page',
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(Exception::class)
        ->and(fn () => DB::table('blog_posts')->insert([
            'title' => 'Dup',
            'slug' => 'tenant-post',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(Exception::class)
        ->and(fn () => DB::table('blog_categories')->insert([
            'name' => 'Dup',
            'slug' => 'tenant-category',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(Exception::class);
});

test('cms settings domain defaults enable blog and pages', function (): void {
    $commerce = app(CommerceSettingService::class);

    $cms = $commerce->getDomain('cms');

    expect($cms['cms.blog_enabled'])->toBeTrue()
        ->and($cms['cms.pages_enabled'])->toBeTrue();

    $updated = $commerce->updateDomain('cms', [
        'cms.blog_enabled' => false,
        'cms.pages_enabled' => false,
    ]);

    expect($updated['cms.blog_enabled'])->toBeFalse()
        ->and($updated['cms.pages_enabled'])->toBeFalse();
});

test('tenant cms permissions isolate customer role', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    expect($admin->can('viewAny', BlogPost::class))->toBeTrue()
        ->and($admin->can('create', Page::class))->toBeTrue()
        ->and($admin->can('cms.publish'))->toBeTrue()
        ->and($customer->can('viewAny', BlogPost::class))->toBeFalse()
        ->and($customer->can('cms.manage'))->toBeFalse();
});

/*
| Tenant isolation note: CMS tables live in each tenant database. Dual-DB
| isolation is enforced by tenancy connection switching rather than a
| tenant_id column on these models. Landlord CMS uses landlord_* tables
| on the central connection to avoid shared-sqlite collisions in tests.
*/
