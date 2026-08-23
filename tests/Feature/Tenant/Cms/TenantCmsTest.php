<?php

declare(strict_types=1);

use App\Enums\Content\ContentStatus;
use App\Models\Tenant\Content\BlogCategory;
use App\Models\Tenant\Content\BlogPost;
use App\Models\Tenant\Content\Page;
use App\Models\Tenant\User;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Content\BlogCategoryService;
use App\Services\Tenant\Content\BlogPostService;
use App\Services\Tenant\Content\PageService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
            '2026_08_16_161108_create_content_pages_table.php',
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
        'status' => ContentStatus::Draft->value,
    ]);

    $post = $postService->store([
        'title' => 'Draft Article',
        'status' => ContentStatus::Draft->value,
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
        'status' => ContentStatus::Published->value,
        'published_at' => now()->subMinute()->toDateTimeString(),
        'seo' => ['meta_title' => 'Shipping'],
    ]);

    $post = $postService->store([
        'title' => 'How to Order',
        'excerpt' => 'A short guide',
        'content' => 'Step by step',
        'status' => ContentStatus::Published->value,
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

test('tenant content slugs are unique', function (): void {
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

test('content settings domain defaults enable blog and pages', function (): void {
    $commerce = app(CommerceSettingService::class);

    $content = $commerce->getDomain('content');

    expect($content['content.blog_enabled'])->toBeTrue()
        ->and($content['content.pages_enabled'])->toBeTrue();

    $updated = $commerce->updateDomain('content', [
        'content.blog_enabled' => false,
        'content.pages_enabled' => false,
    ]);

    expect($updated['content.blog_enabled'])->toBeFalse()
        ->and($updated['content.pages_enabled'])->toBeFalse();
});

test('tenant content permissions isolate customer role', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    expect($admin->can('viewAny', BlogPost::class))->toBeTrue()
        ->and($admin->can('create', Page::class))->toBeTrue()
        ->and($admin->can('content.publish'))->toBeTrue()
        ->and($customer->can('viewAny', BlogPost::class))->toBeFalse()
        ->and($customer->can('content.manage'))->toBeFalse();
});

test('tenant content featured image can be attached and removed', function (): void {
    Storage::fake('public');

    $page = app(PageService::class)->store([
        'title' => 'Media Page',
        'content' => 'Body',
        'status' => ContentStatus::Draft->value,
    ]);

    $updated = app(PageService::class)->storeFeaturedImage(
        $page,
        UploadedFile::fake()->image('hero.jpg', 640, 480),
    );

    expect($updated->getMedia('featured_image'))->toHaveCount(1);

    $cleared = app(PageService::class)->destroyFeaturedImage($updated);

    expect($cleared->getMedia('featured_image'))->toHaveCount(0);
});

/*
| Tenant isolation note: CONTENT tables live in each tenant database. Dual-DB
| isolation is enforced by tenancy connection switching rather than a
| tenant_id column on these models. Landlord CONTENT uses landlord_* tables
| on the central connection to avoid shared-sqlite collisions in tests.
*/
