<?php

declare(strict_types=1);

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductOption;
use App\Models\Tenant\ProductOptionValue;
use App\Models\Tenant\Unit;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Brand\BrandService;
use App\Services\Tenant\Category\CategoryService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Product\ProductService;
use App\Services\Tenant\Product\ProductVariantService;
use App\Services\Tenant\Unit\UnitService;
use App\Services\Tenant\Warehouse\WarehouseService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrationFiles = [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_031731_create_categories_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034251_create_product_options_table.php',
        '2026_08_15_034254_create_product_option_values_table.php',
        '2026_08_15_034257_create_product_attributes_table.php',
        '2026_08_15_034259_create_product_attribute_values_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034307_create_category_product_table.php',
        '2026_08_15_034309_create_product_variant_option_value_table.php',
        '2026_08_15_034312_create_product_attribute_product_table.php',
        '2026_08_15_034315_create_product_prices_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034321_create_inventory_movements_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    Storage::fake('public');
    config([
        'media-library.disk_name' => 'public',
        'media-library.queue_conversions_by_default' => false,
    ]);
});

test('units warehouses and locations can be managed', function (): void {
    $unit = app(UnitService::class)->store([
        'name' => 'Piece',
        'code' => 'pcs',
        'short_name' => 'pc',
    ]);

    expect($unit->code)->toBe('pcs');

    $warehouse = app(WarehouseService::class)->store([
        'name' => 'Main Warehouse',
        'code' => 'MAIN',
        'is_default' => true,
    ]);

    $location = app(WarehouseService::class)->storeLocation($warehouse, [
        'name' => 'Aisle A',
        'code' => 'A-1',
        'aisle' => 'A',
    ]);

    expect($warehouse->fresh()->is_default)->toBeTrue()
        ->and($location->warehouse_id)->toBe($warehouse->id)
        ->and(app(WarehouseService::class)->list()->total())->toBe(1);
});

test('product can be created with brand categories price and media', function (): void {
    $brand = Brand::factory()->create(['name' => 'Nike']);
    $category = Category::factory()->create(['name' => 'Shoes']);
    $unit = Unit::factory()->create(['code' => 'pcs']);

    $product = app(ProductService::class)->store(
        [
            'name' => 'Air Max',
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'type' => ProductType::Simple->value,
            'status' => ProductStatus::Active->value,
            'sku' => 'NIKE-AM-001',
        ],
        UploadedFile::fake()->image('product.jpg', 400, 400),
        [],
        [$category->id],
        ['currency' => 'NGN', 'amount' => '15000.00'],
    );

    expect($product->slug)->toBe('air-max')
        ->and($product->brand_id)->toBe($brand->id)
        ->and($product->categories)->toHaveCount(1)
        ->and($product->prices)->toHaveCount(1)
        ->and($product->getMedia(MediaCollection::Images->value))->toHaveCount(1)
        ->and($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->sku)->toBe('NIKE-AM-001');
});

test('variable product variants reject duplicate option combinations and skus', function (): void {
    $product = app(ProductService::class)->store([
        'name' => 'T-Shirt',
        'type' => ProductType::Variable->value,
        'has_variants' => true,
        'status' => ProductStatus::Draft->value,
    ]);

    $size = ProductOption::query()->create(['name' => 'Size']);
    $color = ProductOption::query()->create(['name' => 'Color']);
    $large = ProductOptionValue::query()->create(['product_option_id' => $size->id, 'value' => 'Large']);
    $red = ProductOptionValue::query()->create(['product_option_id' => $color->id, 'value' => 'Red']);

    $variantService = app(ProductVariantService::class);

    $variantService->store($product, [
        'name' => 'Red Large',
        'sku' => 'TSHIRT-RED-L',
    ], [$large->id, $red->id]);

    expect(fn () => $variantService->store($product, [
        'name' => 'Duplicate combo',
        'sku' => 'TSHIRT-RED-L-2',
    ], [$red->id, $large->id]))->toThrow(ValidationException::class);

    expect(fn () => $variantService->store($product, [
        'name' => 'Other',
        'sku' => 'TSHIRT-RED-L',
    ], []))->toThrow(ValidationException::class);
});

test('inventory adjust reserve release and transfer are safe', function (): void {
    $product = Product::factory()->create();
    $from = Warehouse::factory()->create(['code' => 'WH-A']);
    $to = Warehouse::factory()->create(['code' => 'WH-B']);
    $inventoryService = app(InventoryService::class);

    $inventory = $inventoryService->getOrCreate($from, $product);
    $inventoryService->adjust($inventory, 10, InventoryMovementType::OpeningStock, 'Opening');

    expect($inventory->fresh()->quantity)->toBe(10)
        ->and($inventory->fresh()->availableQuantity())->toBe(10)
        ->and($inventory->movements()->count())->toBe(1);

    $inventoryService->reserve($inventory->fresh(), 3);
    expect($inventory->fresh()->reserved_quantity)->toBe(3)
        ->and($inventory->fresh()->availableQuantity())->toBe(7);

    expect(fn () => $inventoryService->reserve($inventory->fresh(), 8))
        ->toThrow(ValidationException::class);

    $inventoryService->release($inventory->fresh(), 1);
    expect($inventory->fresh()->reserved_quantity)->toBe(2);

    expect(fn () => $inventoryService->adjust($inventory->fresh(), -20, InventoryMovementType::Adjustment))
        ->toThrow(ValidationException::class);

    $destination = $inventoryService->transfer($inventory->fresh(), $to, 4);
    expect($inventory->fresh()->quantity)->toBe(6)
        ->and($destination['to']->quantity)->toBe(4)
        ->and($destination['to']->warehouse_id)->toBe($to->id);
});

test('brand and category deletion blocked when products exist', function (): void {
    $brand = Brand::factory()->create();
    $category = Category::factory()->create();
    $product = app(ProductService::class)->store([
        'name' => 'Linked Product',
        'brand_id' => $brand->id,
        'status' => ProductStatus::Draft->value,
    ], null, [], [$category->id]);

    expect($product->brand_id)->toBe($brand->id);

    expect(fn () => app(BrandService::class)->destroy($brand))
        ->toThrow(ValidationException::class);

    expect(fn () => app(CategoryService::class)->destroy($category))
        ->toThrow(ValidationException::class);
});

test('product list filters by brand category status and search', function (): void {
    $brand = Brand::factory()->create();
    $category = Category::factory()->create();

    app(ProductService::class)->store([
        'name' => 'Samsung TV',
        'brand_id' => $brand->id,
        'status' => ProductStatus::Active->value,
    ], null, [], [$category->id]);

    app(ProductService::class)->store([
        'name' => 'Hidden Draft',
        'status' => ProductStatus::Draft->value,
    ]);

    $service = app(ProductService::class);

    expect($service->list(['search' => 'Samsung'])->total())->toBe(1)
        ->and($service->list(['status' => ProductStatus::Active->value])->total())->toBe(1)
        ->and($service->list(['brand_id' => $brand->id])->total())->toBe(1)
        ->and($service->list(['category_id' => $category->id])->total())->toBe(1);
});

test('unit cannot be deleted when referenced by product', function (): void {
    $unit = Unit::factory()->create();
    app(ProductService::class)->store([
        'name' => 'Unit Product',
        'unit_id' => $unit->id,
        'status' => ProductStatus::Draft->value,
    ]);

    expect(fn () => app(UnitService::class)->destroy($unit))
        ->toThrow(ValidationException::class);
});

test('warehouse cannot be deleted when inventory exists', function (): void {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    app(InventoryService::class)->getOrCreate($warehouse, $product);

    expect(fn () => app(WarehouseService::class)->destroy($warehouse))
        ->toThrow(ValidationException::class);
});

test('product destroy blocked when stock remains', function (): void {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $product);
    app(InventoryService::class)->adjust($inventory, 5, InventoryMovementType::OpeningStock);

    expect(fn () => app(ProductService::class)->destroy($product))
        ->toThrow(ValidationException::class);
});
