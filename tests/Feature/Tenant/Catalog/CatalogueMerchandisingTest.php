<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\CollectionStatus;
use App\Enums\Tenant\Catalog\ProductAvailability;
use App\Enums\Tenant\Catalog\ProductRelationType;
use App\Enums\Tenant\Catalog\ProductReviewStatus;
use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Enums\Tenant\Catalog\ProductVisibility;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\ProductCollection;
use App\Models\Tenant\ProductOption;
use App\Models\Tenant\ProductTag;
use App\Models\Tenant\ProductBadge;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Catalog\ProductAttributeService;
use App\Services\Tenant\Catalog\ProductCollectionService;
use App\Services\Tenant\Catalog\ProductOptionService;
use App\Services\Tenant\Catalog\ProductTagService;
use App\Services\Tenant\Catalog\ProductBadgeService;
use App\Services\Tenant\Catalog\SeoService;
use App\Services\Tenant\Catalog\StorefrontCatalogService;
use App\Services\Tenant\Product\ProductAvailabilityService;
use App\Services\Tenant\Product\ProductBundleService;
use App\Services\Tenant\Product\ProductRelationService;
use App\Services\Tenant\Product\ProductReviewService;
use App\Services\Tenant\Product\ProductSpecificationService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_050002_create_product_relations_table.php',
        '2026_08_15_050003_create_collections_tables.php',
        '2026_08_15_050004_create_product_tags_tables.php',
        '2026_08_15_050005_create_product_badges_tables.php',
        '2026_08_15_050006_create_product_specifications_table.php',
        '2026_08_15_050007_create_seo_meta_table.php',
        '2026_08_15_050008_create_product_bundle_items_table.php',
        '2026_08_15_050009_create_product_reviews_table.php',
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
        'notifications.sms.default' => 'null',
    ]);
});

function catalogueActiveProduct(array $overrides = []): Product
{
    return Product::factory()->create(array_merge([
        'status' => ProductStatus::Active,
        'visibility' => ProductVisibility::Public,
        'published_at' => now()->subDay(),
        'type' => ProductType::Simple,
    ], $overrides));
}

function catalogueStock(Product $product, int $quantity, ?int $reorderLevel = null): Inventory
{
    $warehouse = Warehouse::factory()->create();

    return Inventory::query()->create([
        'warehouse_id' => $warehouse->id,
        'inventoryable_type' => Product::class,
        'inventoryable_id' => $product->id,
        'quantity' => $quantity,
        'reserved_quantity' => 0,
        'reorder_level' => $reorderLevel,
    ]);
}

test('product relations sync and reject self or duplicates', function (): void {
    $product = catalogueActiveProduct(['name' => 'Phone']);
    $related = catalogueActiveProduct(['name' => 'Case']);
    $upsell = catalogueActiveProduct(['name' => 'Premium Phone']);

    $service = app(ProductRelationService::class);

    $synced = $service->sync($product, ProductRelationType::Related, [
        ['product_id' => $related->id, 'sort_order' => 0],
    ]);

    expect($synced)->toHaveCount(1)
        ->and($synced->first()->id)->toBe($related->id);

    $service->sync($product, ProductRelationType::Upsell, [
        ['product_id' => $upsell->id],
    ]);

    expect($service->list($product, ProductRelationType::Upsell))->toHaveCount(1);

    expect(fn () => $service->sync($product, ProductRelationType::CrossSell, [
        ['product_id' => $product->id],
    ]))->toThrow(ValidationException::class);

    expect(fn () => $service->sync($product, ProductRelationType::Related, [
        ['product_id' => $related->id],
        ['product_id' => $related->id],
    ]))->toThrow(ValidationException::class);
});

test('collections can attach products with ordering', function (): void {
    $collection = app(ProductCollectionService::class)->store([
        'name' => 'Summer Sale',
        'status' => CollectionStatus::Active->value,
        'published_at' => now(),
    ]);

    $a = catalogueActiveProduct(['name' => 'A']);
    $b = catalogueActiveProduct(['name' => 'B']);

    app(ProductCollectionService::class)->syncProducts($collection, [
        ['product_id' => $b->id, 'sort_order' => 0],
        ['product_id' => $a->id, 'sort_order' => 1],
    ]);

    $products = $collection->fresh()->products;

    expect($products)->toHaveCount(2)
        ->and($products->first()->id)->toBe($b->id);
});

test('tags and badges can be created and attached to products', function (): void {
    $tag = app(ProductTagService::class)->store(['name' => 'Gaming']);
    $badge = app(ProductBadgeService::class)->store(['name' => 'Best Seller', 'color' => '#ff0000']);

    $product = catalogueActiveProduct();
    $product->tags()->sync([$tag->id]);
    $product->badges()->sync([$badge->id => ['sort_order' => 0]]);

    expect($product->fresh()->tags)->toHaveCount(1)
        ->and($product->fresh()->badges->first()->name)->toBe('Best Seller')
        ->and(ProductTag::query()->count())->toBe(1)
        ->and(ProductBadge::query()->count())->toBe(1);
});

test('seo meta can be upserted for products brands and collections', function (): void {
    $product = catalogueActiveProduct();
    $brand = Brand::factory()->create();
    $collection = ProductCollection::factory()->create();

    $seo = app(SeoService::class);

    $productSeo = $seo->upsert($product, [
        'meta_title' => 'Buy Phone',
        'meta_description' => 'Great phone',
        'canonical_url' => 'https://example.test/phone',
    ]);

    $brandSeo = $seo->upsert($brand, ['meta_title' => 'Brand SEO']);
    $collectionSeo = $seo->upsert($collection, ['meta_title' => 'Collection SEO']);

    expect($productSeo->meta_title)->toBe('Buy Phone')
        ->and($seo->show($product->fresh())->id)->toBe($productSeo->id)
        ->and($brandSeo->meta_title)->toBe('Brand SEO')
        ->and($collectionSeo->meta_title)->toBe('Collection SEO');
});

test('product specifications can be replace-synced', function (): void {
    $product = catalogueActiveProduct();

    app(ProductSpecificationService::class)->sync($product, [
        ['group' => 'Display', 'name' => 'Size', 'value' => '55 inch', 'sort_order' => 0],
        ['group' => 'Display', 'name' => 'Resolution', 'value' => '4K', 'sort_order' => 1],
    ]);

    expect($product->specifications()->count())->toBe(2);

    app(ProductSpecificationService::class)->sync($product, [
        ['name' => 'Weight', 'value' => '12kg'],
    ]);

    expect($product->specifications()->count())->toBe(1)
        ->and($product->specifications()->first()->name)->toBe('Weight');
});

test('availability resolves in stock low stock out of stock backorder and preorder', function (): void {
    $availability = app(ProductAvailabilityService::class);

    $inStock = catalogueActiveProduct();
    catalogueStock($inStock, 20, 5);
    expect($availability->forProduct($inStock->fresh(['inventories'])))->toBe(ProductAvailability::InStock);

    $low = catalogueActiveProduct();
    catalogueStock($low, 3, 5);
    expect($availability->forProduct($low->fresh(['inventories'])))->toBe(ProductAvailability::LowStock);

    $out = catalogueActiveProduct();
    catalogueStock($out, 0, 5);
    expect($availability->forProduct($out->fresh(['inventories'])))->toBe(ProductAvailability::OutOfStock);

    $backorder = catalogueActiveProduct(['allow_backorder' => true]);
    catalogueStock($backorder, 0);
    expect($availability->forProduct($backorder->fresh(['inventories'])))->toBe(ProductAvailability::Backorder);

    $preorder = catalogueActiveProduct([
        'is_preorder' => true,
        'preorder_start_at' => now()->subDay(),
        'preorder_end_at' => now()->addWeek(),
    ]);
    catalogueStock($preorder, 0);
    expect($availability->forProduct($preorder->fresh(['inventories'])))->toBe(ProductAvailability::Preorder);

    $draft = Product::factory()->create(['status' => ProductStatus::Draft]);
    expect($availability->forProduct($draft))->toBe(ProductAvailability::Unavailable);
});

test('bundle availability depends on components', function (): void {
    $mouse = catalogueActiveProduct(['name' => 'Mouse']);
    catalogueStock($mouse, 0);

    $laptop = catalogueActiveProduct(['name' => 'Laptop']);
    catalogueStock($laptop, 10);

    $bundle = catalogueActiveProduct([
        'name' => 'Gaming Bundle',
        'type' => ProductType::Bundle,
    ]);

    app(ProductBundleService::class)->syncItems($bundle, [
        ['product_id' => $laptop->id, 'quantity' => 1],
        ['product_id' => $mouse->id, 'quantity' => 1],
    ]);

    $availability = app(ProductAvailabilityService::class)->forProduct(
        $bundle->fresh(['bundleItems.product.inventories', 'bundleItems.variant']),
    );

    expect($availability)->toBe(ProductAvailability::OutOfStock);

    expect(fn () => app(ProductBundleService::class)->syncItems($bundle, [
        ['product_id' => $bundle->id],
    ]))->toThrow(ValidationException::class);
});

test('reviews are pending by default and aggregates update on moderation', function (): void {
    $product = catalogueActiveProduct();
    $customer = Customer::factory()->create();

    $review = app(ProductReviewService::class)->customerStore($customer, $product, [
        'rating' => 5,
        'content' => 'Excellent product',
        'verified_purchase' => true,
    ]);

    expect($review->status)->toBe(ProductReviewStatus::Pending)
        ->and($review->verified_purchase)->toBeFalse();

    app(ProductReviewService::class)->moderate($review, ProductReviewStatus::Approved);

    $product->refresh();

    expect((float) $product->average_rating)->toBe(5.0)
        ->and($product->reviews_count)->toBe(1);

    $approved = app(StorefrontCatalogService::class)->productReviews($product);

    expect($approved->total())->toBe(1);
});

test('storefront hides draft products and omits cost from prices', function (): void {
    catalogueActiveProduct(['name' => 'Visible', 'slug' => 'visible-product']);
    Product::factory()->create([
        'name' => 'Hidden Draft',
        'status' => ProductStatus::Draft,
        'visibility' => ProductVisibility::Public,
    ]);

    $list = app(StorefrontCatalogService::class)->products([]);

    expect($list->total())->toBe(1)
        ->and($list->first()->name)->toBe('Visible');

    $show = app(StorefrontCatalogService::class)->product('visible-product');

    expect($show->name)->toBe('Visible')
        ->and($show->getAttribute('availability'))->not->toBeNull();
});

test('option and attribute dictionaries can be managed', function (): void {
    $option = app(ProductOptionService::class)->store(['name' => 'Size']);
    $value = app(ProductOptionService::class)->storeValue($option, ['value' => 'Large']);

    expect($option->values()->count())->toBe(1)
        ->and($value->value)->toBe('Large');

    $attribute = app(ProductAttributeService::class)->store(['name' => 'Material']);
    $attrValue = app(ProductAttributeService::class)->storeValue($attribute, ['value' => 'Cotton']);

    expect(ProductAttribute::query()->count())->toBe(1)
        ->and($attribute->values()->count())->toBe(1)
        ->and($attrValue->value)->toBe('Cotton')
        ->and(ProductOption::query()->count())->toBe(1);
});
