<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Product\ProductService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;

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

    $this->withoutMiddleware([
        InitializeTenancyByDomain::class,
        PreventAccessFromUnwantedDomains::class,
        EnsureActiveSubscription::class,
    ]);
});

function inventoryAdmin(): User
{
    $user = User::factory()->create();
    $user->syncRoles(['admin']);
    Sanctum::actingAs($user, ['*'], 'tenant');

    return $user;
}

test('POST /api/inventory assigns a product to a warehouse', function (): void {
    inventoryAdmin();

    $product = app(ProductService::class)->store([
        'name' => 'Samsung TV',
        'type' => ProductType::Simple->value,
        'status' => ProductStatus::Active->value,
        'sku' => 'SAMSUNG-TV-001',
    ]);
    $warehouse = Warehouse::factory()->create(['code' => 'LOS']);

    $response = $this->postJson('/api/inventory', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => 20,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.quantity', 20)
        ->assertJsonPath('data.warehouse_id', $warehouse->id);

    expect(Product::query()->count())->toBe(1)
        ->and(Inventory::query()->count())->toBe(1);
});

test('GET /api/warehouses/{warehouse}/inventory lists warehouse stock', function (): void {
    inventoryAdmin();

    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create(['code' => 'LOS']);
    $other = Warehouse::factory()->create(['code' => 'ABV']);

    $inventoryService = app(InventoryService::class);
    $inventoryService->assign($warehouse, $product, null, ['quantity' => 12]);
    $inventoryService->assign($other, $product, null, ['quantity' => 4]);

    $response = $this->getJson("/api/warehouses/{$warehouse->id}/inventory");

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', 12);
});

test('GET /api/products/{product} exposes warehouse stock totals', function (): void {
    inventoryAdmin();

    $product = app(ProductService::class)->store([
        'name' => 'Samsung TV',
        'type' => ProductType::Simple->value,
        'status' => ProductStatus::Active->value,
        'sku' => 'SAMSUNG-TV-001',
    ]);

    $lagos = Warehouse::factory()->create(['code' => 'LOS']);
    $abuja = Warehouse::factory()->create(['code' => 'ABV']);
    $inventoryService = app(InventoryService::class);
    $inventoryService->assign($lagos, $product, null, ['quantity' => 20]);
    $inventoryService->assign($abuja, $product, null, ['quantity' => 10]);

    $response = $this->getJson("/api/products/{$product->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.total_quantity', 30)
        ->assertJsonPath('data.total_available_quantity', 30)
        ->assertJsonCount(2, 'data.warehouses');
});

test('DELETE /api/inventory/{inventory} removes an empty assignment', function (): void {
    inventoryAdmin();

    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $inventory = app(InventoryService::class)->assign($warehouse, $product);

    $response = $this->deleteJson("/api/inventory/{$inventory->id}");

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    expect(Inventory::query()->whereKey($inventory->id)->exists())->toBeFalse();
});

test('inventory index filters by product_id', function (): void {
    inventoryAdmin();

    $first = Product::factory()->create(['name' => 'TV A']);
    $second = Product::factory()->create(['name' => 'TV B']);
    $warehouse = Warehouse::factory()->create();
    $inventoryService = app(InventoryService::class);
    $inventoryService->assign($warehouse, $first, null, ['quantity' => 5]);
    $inventoryService->assign($warehouse, $second, null, ['quantity' => 3]);

    $response = $this->getJson('/api/inventory?product_id='.$first->id);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});
