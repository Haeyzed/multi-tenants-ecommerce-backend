<?php

declare(strict_types=1);

use App\Enums\Media\MediaCollection;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use App\Models\Tenant\User;
use App\Services\Tenant\Brand\BrandService;
use App\Services\Tenant\Category\CategoryService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_15_031728_create_brands_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_15_031731_create_categories_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);

    Storage::fake('public');
    config([
        'media-library.disk_name' => 'public',
        'media-library.queue_conversions_by_default' => false,
    ]);
});

function catalogAdmin(): User
{
    $user = User::factory()->create();
    $user->syncRoles(['admin']);

    return $user;
}

test('brand service creates updates lists and deletes brands', function (): void {
    $service = app(BrandService::class);

    $brand = $service->store([
        'name' => 'Samsung',
        'description' => 'Electronics',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    expect($brand->slug)->toBe('samsung')
        ->and($brand->is_active)->toBeTrue();

    $updated = $service->update($brand, ['name' => 'Samsung Corp']);
    expect($updated->name)->toBe('Samsung Corp')
        ->and($updated->slug)->toBe('samsung');

    $listed = $service->list(['search' => 'Samsung']);
    expect($listed->total())->toBe(1);

    $service->destroy($updated);
    expect(Brand::query()->whereKey($brand->id)->exists())->toBeFalse();
});

test('brand rejects duplicate names within tenant', function (): void {
    Brand::factory()->create(['name' => 'Apple']);

    expect(fn () => Brand::factory()->create(['name' => 'Apple']))
        ->toThrow(Exception::class);
});

test('brand logo upload replace and delete', function (): void {
    $service = app(BrandService::class);
    $brand = $service->store(['name' => 'Sony']);

    $withLogo = $service->storeLogo($brand, UploadedFile::fake()->image('logo.png', 200, 200));
    expect($withLogo->getMedia(MediaCollection::Logo->value))->toHaveCount(1);

    $replaced = $service->storeLogo($withLogo, UploadedFile::fake()->image('logo2.jpg', 180, 180));
    expect($replaced->getMedia(MediaCollection::Logo->value))->toHaveCount(1);

    $cleared = $service->destroyLogo($replaced);
    expect($cleared->getMedia(MediaCollection::Logo->value))->toHaveCount(0);
});

test('brand list filters by is_active and sorts', function (): void {
    Brand::factory()->create(['name' => 'Active Co', 'is_active' => true, 'sort_order' => 2]);
    Brand::factory()->inactive()->create(['name' => 'Inactive Co', 'sort_order' => 1]);

    $service = app(BrandService::class);

    expect($service->list(['is_active' => true])->total())->toBe(1)
        ->and($service->list(['sort' => 'name'])->first()->name)->toBe('Active Co')
        ->and($service->list(['sort' => '-sort_order'])->first()->sort_order)->toBe(2);
});

test('category hierarchy create tree and children', function (): void {
    $service = app(CategoryService::class);

    $electronics = $service->store(['name' => 'Electronics', 'sort_order' => 1]);
    $phones = $service->store(['name' => 'Phones', 'parent_id' => $electronics->id, 'sort_order' => 1]);
    $service->store(['name' => 'Android', 'parent_id' => $phones->id, 'sort_order' => 1]);

    $tree = $service->tree();
    expect($tree)->toHaveCount(1)
        ->and($tree->first()->name)->toBe('Electronics')
        ->and($tree->first()->children)->toHaveCount(1)
        ->and($tree->first()->children->first()->children)->toHaveCount(1);

    expect($service->children($electronics))->toHaveCount(1)
        ->and($service->list(['root' => true])->total())->toBe(1)
        ->and($service->list(['parent_id' => $electronics->id])->total())->toBe(1);
});

test('category allows same name under different parents', function (): void {
    $service = app(CategoryService::class);

    $electronics = $service->store(['name' => 'Electronics']);
    $fashion = $service->store(['name' => 'Fashion']);

    $a = $service->store(['name' => 'Accessories', 'parent_id' => $electronics->id]);
    $b = $service->store(['name' => 'Accessories', 'parent_id' => $fashion->id]);

    expect($a->name)->toBe('Accessories')
        ->and($b->name)->toBe('Accessories')
        ->and($a->slug)->not->toBe($b->slug);
});

test('category prevents self parent and circular hierarchy', function (): void {
    $service = app(CategoryService::class);

    $a = $service->store(['name' => 'A']);
    $b = $service->store(['name' => 'B', 'parent_id' => $a->id]);
    $c = $service->store(['name' => 'C', 'parent_id' => $b->id]);

    expect(fn () => $service->update($a, ['parent_id' => $a->id]))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->update($a, ['parent_id' => $c->id]))
        ->toThrow(ValidationException::class);
});

test('category cannot be deleted with children', function (): void {
    $service = app(CategoryService::class);
    $parent = $service->store(['name' => 'Parent']);
    $service->store(['name' => 'Child', 'parent_id' => $parent->id]);

    expect(fn () => $service->destroy($parent))
        ->toThrow(ValidationException::class);
});

test('category image upload replace and delete', function (): void {
    $service = app(CategoryService::class);
    $category = $service->store(['name' => 'Gadgets']);

    $withImage = $service->storeImage($category, UploadedFile::fake()->image('cat.png', 300, 300));
    expect($withImage->getMedia(MediaCollection::Image->value))->toHaveCount(1);

    $replaced = $service->storeImage($withImage, UploadedFile::fake()->image('cat2.jpg', 250, 250));
    expect($replaced->getMedia(MediaCollection::Image->value))->toHaveCount(1);

    $cleared = $service->destroyImage($replaced);
    expect($cleared->getMedia(MediaCollection::Image->value))->toHaveCount(0);
});

test('brand and category policies respect rbac permissions', function (): void {
    $admin = catalogAdmin();
    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    $brand = Brand::factory()->create();
    $category = Category::factory()->create();

    expect($admin->can('viewAny', Brand::class))->toBeTrue()
        ->and($admin->can('update', $brand))->toBeTrue()
        ->and($admin->can('viewAny', Category::class))->toBeTrue()
        ->and($admin->can('delete', $category))->toBeTrue()
        ->and($customer->can('viewAny', Brand::class))->toBeFalse()
        ->and($customer->can('create', Category::class))->toBeFalse();
});

test('brand options return label value pairs', function (): void {
    Brand::factory()->create(['name' => 'Nike']);

    $options = app(BrandService::class)->options();

    expect($options->first())->toHaveKeys(['label', 'value'])
        ->and($options->first()['label'])->toBe('Nike');
});

test('unauthenticated tenant user cannot rely on brand permissions', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*'], 'tenant');

    expect($user->can('brands.view'))->toBeFalse();
});
