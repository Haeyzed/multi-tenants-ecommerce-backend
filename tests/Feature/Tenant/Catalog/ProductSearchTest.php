<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\ProductAvailability;
use App\Enums\Tenant\Catalog\ProductSearchSort;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\ProductAttributeValue;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Catalog\ProductSearchService;
use App\Services\Tenant\Catalog\StorefrontCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (productSearchMigrationFiles() as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

/**
 * @return list<string>
 */
function productSearchMigrationFiles(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_031731_create_categories_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034257_create_product_attributes_table.php',
        '2026_08_15_034259_create_product_attribute_values_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034307_create_category_product_table.php',
        '2026_08_15_034312_create_product_attribute_product_table.php',
        '2026_08_15_034315_create_product_prices_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034321_create_inventory_movements_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_050003_create_collections_tables.php',
        '2026_08_15_050004_create_product_tags_tables.php',
        '2026_08_15_050005_create_product_badges_tables.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_16_160758_make_sellers_authenticatable_table.php',
        '2026_08_15_070003_create_seller_offers_table.php',
    ];
}

/**
 * Create an active, publicly visible product with a single active price.
 *
 * @param  array<string, mixed>  $attributes
 */
function searchableProduct(string $name, string $price = '100.00', array $attributes = []): Product
{
    $product = Product::factory()->active()->create(array_merge(['name' => $name], $attributes));

    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => $price,
        'is_active' => true,
    ]);

    return $product;
}

function stockProduct(Product $product, int $quantity, int $reserved = 0): Inventory
{
    /** @var Inventory $inventory */
    $inventory = Inventory::query()->create([
        'warehouse_id' => Warehouse::factory()->create()->id,
        'inventoryable_type' => Product::class,
        'inventoryable_id' => $product->id,
        'quantity' => $quantity,
        'reserved_quantity' => $reserved,
    ]);

    return $inventory;
}

test('keyword search matches product name and ignores non matches', function (): void {
    searchableProduct('Aurora Running Shoe');
    searchableProduct('Cast Iron Skillet');

    $results = app(ProductSearchService::class)->search(['keyword' => 'Aurora']);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->name)->toBe('Aurora Running Shoe');
});

test('price range filter uses active product prices', function (): void {
    searchableProduct('Budget Lamp', '25.00');
    searchableProduct('Mid Lamp', '150.00');
    searchableProduct('Premium Lamp', '900.00');

    $results = app(ProductSearchService::class)->search([
        'min_price' => '50.00',
        'max_price' => '400.00',
    ]);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->name)->toBe('Mid Lamp');
});

test('rating brand and category filters narrow results', function (): void {
    $brand = Brand::factory()->create();
    $category = Category::factory()->create();

    $match = searchableProduct('Top Rated Mug', '20.00', [
        'brand_id' => $brand->id,
        'average_rating' => '4.80',
    ]);
    $match->categories()->attach($category);

    searchableProduct('Poorly Rated Mug', '20.00', ['average_rating' => '2.10']);

    $service = app(ProductSearchService::class);

    expect($service->search(['min_rating' => 4])->total())->toBe(1)
        ->and($service->search(['brand_id' => $brand->id])->total())->toBe(1)
        ->and($service->search(['category_id' => $category->id])->items()[0]->id)->toBe($match->id);
});

test('availability filter only returns purchasable products', function (): void {
    $inStock = searchableProduct('Stocked Chair');
    stockProduct($inStock, 5);

    $outOfStock = searchableProduct('Sold Out Chair');
    stockProduct($outOfStock, 2, 2);

    $results = app(ProductSearchService::class)->search([
        'availability' => ProductAvailability::InStock->value,
    ]);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->id)->toBe($inStock->id);
});

test('attribute value filter requires every selected value', function (): void {
    /** @var ProductAttribute $attribute */
    $attribute = ProductAttribute::query()->create(['name' => 'Variant Traits']);

    /** @var ProductAttributeValue $red */
    $red = ProductAttributeValue::query()->create([
        'product_attribute_id' => $attribute->id,
        'value' => 'Red',
    ]);

    /** @var ProductAttributeValue $large */
    $large = ProductAttributeValue::query()->create([
        'product_attribute_id' => $attribute->id,
        'value' => 'Large',
    ]);

    $both = searchableProduct('Red Large Tee');
    $both->attributeValues()->attach([$red->id, $large->id]);

    $onlyRed = searchableProduct('Red Small Tee');
    $onlyRed->attributeValues()->attach([$red->id]);

    $results = app(ProductSearchService::class)->search([
        'attribute_value_ids' => [$red->id, $large->id],
    ]);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->id)->toBe($both->id);
});

test('price sorts order by lowest active price', function (): void {
    searchableProduct('Cheap Desk', '30.00');
    searchableProduct('Costly Desk', '300.00');

    $service = app(ProductSearchService::class);

    $ascending = $service->search(['sort' => ProductSearchSort::PriceAsc->value]);
    $descending = $service->search(['sort' => ProductSearchSort::PriceDesc->value]);

    expect($ascending->items()[0]->name)->toBe('Cheap Desk')
        ->and($descending->items()[0]->name)->toBe('Costly Desk');
});

test('relevance sort ranks exact name matches first', function (): void {
    searchableProduct('Kettle Deluxe Edition');
    searchableProduct('Kettle');
    searchableProduct('Electric Kettle Stand');

    $results = app(ProductSearchService::class)->search([
        'keyword' => 'Kettle',
        'sort' => ProductSearchSort::Relevance->value,
    ]);

    expect($results->total())->toBe(3)
        ->and($results->items()[0]->name)->toBe('Kettle')
        ->and($results->items()[1]->name)->toBe('Kettle Deluxe Edition');
});

test('rating and popularity sorts use review scores and sold quantity', function (): void {
    $popular = searchableProduct('Popular Pan', '40.00', ['average_rating' => '3.00']);
    $highlyRated = searchableProduct('Rated Pan', '40.00', ['average_rating' => '5.00']);

    $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $popular->id,
        'product_name' => $popular->name,
        'quantity' => 9,
        'unit_price' => '40.00',
        'subtotal' => '360.00',
        'total' => '360.00',
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $highlyRated->id,
        'product_name' => $highlyRated->name,
        'quantity' => 1,
        'unit_price' => '40.00',
        'subtotal' => '40.00',
        'total' => '40.00',
    ]);

    $service = app(ProductSearchService::class);

    expect($service->search(['sort' => ProductSearchSort::Rating->value])->items()[0]->id)->toBe($highlyRated->id)
        ->and($service->search(['sort' => ProductSearchSort::Popularity->value])->items()[0]->id)->toBe($popular->id);
});

test('storefront catalog delegates to product search and attaches availability', function (): void {
    $product = searchableProduct('Storefront Bottle', '15.00');
    stockProduct($product, 12);

    Product::factory()->create(['name' => 'Draft Bottle']);

    $results = app(StorefrontCatalogService::class)->products(['keyword' => 'Bottle']);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->getAttribute('availability'))->toBe(ProductAvailability::InStock->value);
});

test('unknown sort values fall back to the default catalogue ordering', function (): void {
    searchableProduct('Alpha Widget', '10.00', ['sort_order' => 2]);
    searchableProduct('Beta Widget', '10.00', ['sort_order' => 1]);

    $results = app(ProductSearchService::class)->search(['sort' => 'not-a-real-sort']);

    expect($results->items()[0]->name)->toBe('Beta Widget');
});
