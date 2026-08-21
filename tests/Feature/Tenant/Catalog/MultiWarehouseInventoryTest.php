<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Catalog\ProductAvailability;
use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Http\Resources\Tenant\Product\ProductVariantResource;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\InventoryStockableResolver;
use App\Services\Tenant\Product\ProductAvailabilityService;
use App\Services\Tenant\Product\ProductService;
use App\Services\Tenant\Product\ProductVariantService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        '2026_08_15_050002_create_product_relations_table.php',
        '2026_08_15_050003_create_collections_tables.php',
        '2026_08_15_050004_create_product_tags_tables.php',
        '2026_08_15_050005_create_product_badges_tables.php',
        '2026_08_15_050006_create_product_specifications_table.php',
        '2026_08_15_050007_create_seo_meta_table.php',
        '2026_08_15_050008_create_product_bundle_items_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

test('product can exist without warehouse assignment', function (): void {
    $product = app(ProductService::class)->store([
        'name' => 'Samsung TV',
        'type' => ProductType::Simple->value,
        'status' => ProductStatus::Draft->value,
        'sku' => 'SAMSUNG-TV-001',
    ]);

    expect(Schema::hasColumn('products', 'warehouse_id'))->toBeFalse()
        ->and(Product::query()->count())->toBe(1)
        ->and(Inventory::query()->count())->toBe(0)
        ->and($product->variants->first()->sku)->toBe('SAMSUNG-TV-001');
});

test('product can be assigned to multiple warehouses without duplicating the catalogue', function (): void {
    $product = app(ProductService::class)->store([
        'name' => 'Samsung TV',
        'type' => ProductType::Simple->value,
        'status' => ProductStatus::Active->value,
        'sku' => 'SAMSUNG-TV-001',
    ]);
    $product->variants->first()?->update(['barcode' => '8801643221234']);

    $lagos = Warehouse::factory()->create(['name' => 'Lagos', 'code' => 'LOS']);
    $abuja = Warehouse::factory()->create(['name' => 'Abuja', 'code' => 'ABV']);
    $ibadan = Warehouse::factory()->create(['name' => 'Ibadan', 'code' => 'IBA']);
    $inventoryService = app(InventoryService::class);

    $inventoryService->assign($lagos, $product, null, ['quantity' => 20]);
    $inventoryService->assign($abuja, $product, null, ['quantity' => 10]);
    $inventoryService->assign($ibadan, $product, null, ['quantity' => 5]);

    $again = $inventoryService->assign($lagos, $product, null, ['quantity' => 99]);

    expect(Product::query()->where('name', 'Samsung TV')->count())->toBe(1)
        ->and(Inventory::query()->count())->toBe(3)
        ->and($again->quantity)->toBe(20)
        ->and($product->fresh()->variants->first()->sku)->toBe('SAMSUNG-TV-001')
        ->and($product->fresh()->variants->first()->barcode)->toBe('8801643221234');

    $payload = (new ProductResource(app(ProductService::class)->show($product->fresh())))
        ->toArray(Request::create('/'));

    expect($payload['total_quantity'])->toBe(35)
        ->and($payload['total_available_quantity'])->toBe(35)
        ->and($payload['warehouses'])->toHaveCount(3);
});

test('warehouse can contain many products and stock adjustments stay isolated', function (): void {
    $warehouse = Warehouse::factory()->create(['code' => 'LOS']);
    $other = Warehouse::factory()->create(['code' => 'ABV']);
    $tv = Product::factory()->create(['name' => 'Samsung TV']);
    $phone = Product::factory()->create(['name' => 'Galaxy Phone']);
    $inventoryService = app(InventoryService::class);

    $tvLagos = $inventoryService->assign($warehouse, $tv, null, ['quantity' => 8]);
    $inventoryService->assign($warehouse, $phone, null, ['quantity' => 3]);
    $tvAbuja = $inventoryService->assign($other, $tv, null, ['quantity' => 4]);

    $inventoryService->adjust($tvLagos, -2, InventoryMovementType::Adjustment, 'Damage');

    expect($tvLagos->fresh()->quantity)->toBe(6)
        ->and($tvAbuja->fresh()->quantity)->toBe(4)
        ->and($inventoryService->list(['warehouse_id' => $warehouse->id])->total())->toBe(2)
        ->and($inventoryService->list(['product_id' => $tv->id])->total())->toBe(2);
});

test('product variant can exist in multiple warehouses', function (): void {
    $product = app(ProductService::class)->store([
        'name' => 'Samsung TV',
        'type' => ProductType::Variable->value,
        'has_variants' => true,
        'status' => ProductStatus::Active->value,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => '55 Black',
        'sku' => 'SAMSUNG-TV-001-55-BLK',
    ]);

    $lagos = Warehouse::factory()->create(['code' => 'LOS']);
    $abuja = Warehouse::factory()->create(['code' => 'ABV']);
    $inventoryService = app(InventoryService::class);

    $inventoryService->assign($lagos, $product, $variant, ['quantity' => 10]);
    $inventoryService->assign($abuja, $product, $variant, ['quantity' => 5]);

    expect(Product::query()->count())->toBe(1)
        ->and(ProductVariant::query()->where('sku', 'SAMSUNG-TV-001-55-BLK')->count())->toBe(1)
        ->and(Inventory::query()->count())->toBe(2);
});

test('stock transfer moves quantity between warehouses for the same catalogue item', function (): void {
    $product = Product::factory()->create();
    $lagos = Warehouse::factory()->create(['code' => 'LOS']);
    $abuja = Warehouse::factory()->create(['code' => 'ABV']);
    $inventoryService = app(InventoryService::class);

    $from = $inventoryService->assign($lagos, $product, null, ['quantity' => 20]);
    $result = $inventoryService->transfer($from, $abuja, 5);

    expect($from->fresh()->quantity)->toBe(15)
        ->and($result['to']->quantity)->toBe(5)
        ->and($result['to']->warehouse_id)->toBe($abuja->id)
        ->and($from->fresh()->movements()->where('type', InventoryMovementType::TransferOut)->count())->toBe(1)
        ->and($result['to']->movements()->where('type', InventoryMovementType::TransferIn)->count())->toBe(1);
});

test('empty inventory assignment can be removed but stocked inventory cannot', function (): void {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $inventoryService = app(InventoryService::class);

    $empty = $inventoryService->assign($warehouse, $product);
    $stocked = $inventoryService->assign(Warehouse::factory()->create(), $product, null, ['quantity' => 2]);

    $inventoryService->unassign($empty);

    expect(Inventory::query()->whereKey($empty->id)->exists())->toBeFalse();

    expect(fn () => $inventoryService->unassign($stocked->fresh()))
        ->toThrow(ValidationException::class);
});

test('simple product sku stock is visible to availability and reservation lookup', function (): void {
    $product = app(ProductService::class)->store([
        'name' => 'Samsung TV',
        'type' => ProductType::Simple->value,
        'status' => ProductStatus::Active->value,
        'sku' => 'SAMSUNG-TV-001',
        'published_at' => now(),
    ]);

    $warehouse = Warehouse::factory()->create(['is_default' => true, 'code' => 'LOS']);
    app(InventoryService::class)->assign($warehouse, $product, null, ['quantity' => 12]);

    $stockable = app(InventoryStockableResolver::class)->resolve($product->fresh());
    expect($stockable)->toBeInstanceOf(ProductVariant::class)
        ->and($stockable->inventories()->sum('quantity'))->toBe(12);

    $availability = app(ProductAvailabilityService::class)->forProduct($product->fresh());
    expect($availability)->toBe(ProductAvailability::InStock);

    $inventories = Inventory::query();
    app(InventoryStockableResolver::class)->constrainInventoryQuery($inventories, $product->fresh());
    $preferred = $inventories->get()->first();

    expect($preferred)->not->toBeNull()
        ->and($preferred->warehouse_id)->toBe($warehouse->id)
        ->and($preferred->availableQuantity())->toBe(12);
});

test('reservation and concurrent oversell are rejected against warehouse inventory', function (): void {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $inventoryService = app(InventoryService::class);
    $inventory = $inventoryService->assign($warehouse, $product, null, ['quantity' => 5]);

    $inventoryService->reserve($inventory->fresh(), 3);

    expect($inventory->fresh()->reserved_quantity)->toBe(3)
        ->and($inventory->fresh()->availableQuantity())->toBe(2)
        ->and($inventory->fresh()->quantity)->toBe(5);

    expect(fn () => $inventoryService->reserve($inventory->fresh(), 3))
        ->toThrow(ValidationException::class);
});

test('duplicate inventory rows for the same warehouse and stockable are prevented', function (): void {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $inventoryService = app(InventoryService::class);

    $first = $inventoryService->getOrCreate($warehouse, $product);
    $second = $inventoryService->getOrCreate($warehouse, $product);

    expect($first->id)->toBe($second->id)
        ->and(Inventory::query()->count())->toBe(1);
});

test('product variant show exposes warehouse stock totals', function (): void {
    $product = app(ProductService::class)->store([
        'name' => 'Samsung TV',
        'type' => ProductType::Variable->value,
        'has_variants' => true,
        'status' => ProductStatus::Active->value,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => '55 Black',
        'sku' => 'SAMSUNG-TV-001-55-BLK',
    ]);

    $lagos = Warehouse::factory()->create(['code' => 'LOS']);
    $abuja = Warehouse::factory()->create(['code' => 'ABV']);
    $inventoryService = app(InventoryService::class);
    $inventoryService->assign($lagos, $product, $variant, ['quantity' => 10]);
    $inventoryService->assign($abuja, $product, $variant, ['quantity' => 5]);

    $payload = (new ProductVariantResource(
        app(ProductVariantService::class)->show($variant->fresh()),
    ))->toArray(Request::create('/'));

    expect($payload['total_quantity'])->toBe(15)
        ->and($payload['total_available_quantity'])->toBe(15)
        ->and($payload['inventory'])->toHaveCount(2);
});
