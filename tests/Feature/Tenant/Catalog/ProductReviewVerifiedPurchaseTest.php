<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\ProductReviewStatus;
use App\Enums\Tenant\Commerce\FulfillmentStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductReview;
use App\Services\Tenant\Product\ProductReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (reviewVerifiedPurchaseMigrationFiles() as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    config([
        'notifications.queue' => false,
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
    ]);
});

/**
 * @return list<string>
 */
function reviewVerifiedPurchaseMigrationFiles(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034315_create_product_prices_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034321_create_inventory_movements_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_041028_create_customer_addresses_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_050009_create_product_reviews_table.php',
        '2026_08_15_090203_add_unique_customer_product_to_product_reviews_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
    ];
}

/**
 * @return array{customer: Customer, product: Product}
 */
function completedPaidOrderFixture(): array
{
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['brand_id' => null]);

    $order = Order::query()->create([
        'order_number' => 'ORD-REVIEW-1',
        'customer_id' => $customer->id,
        'currency' => 'NGN',
        'status' => OrderStatus::Completed,
        'payment_status' => OrderPaymentStatus::Paid,
        'fulfillment_status' => FulfillmentStatus::Unfulfilled,
        'subtotal' => '100.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'shipping_total' => '0.00',
        'grand_total' => '100.00',
        'placed_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => '100.00',
        'subtotal' => '100.00',
        'total' => '100.00',
    ]);

    return [
        'customer' => $customer,
        'product' => $product,
    ];
}

test('review ignores client verified_purchase flag without completed paid order', function (): void {
    $product = Product::factory()->active()->create(['brand_id' => null]);
    $customer = Customer::factory()->create();

    $review = app(ProductReviewService::class)->customerStore($customer, $product, [
        'rating' => 5,
        'content' => 'Great',
        'verified_purchase' => true,
    ]);

    expect($review->verified_purchase)->toBeFalse()
        ->and($review->status)->toBe(ProductReviewStatus::Pending);
});

test('review marks verified purchase for completed paid order containing product', function (): void {
    $fixture = completedPaidOrderFixture();

    $review = app(ProductReviewService::class)->customerStore($fixture['customer'], $fixture['product'], [
        'rating' => 5,
        'content' => 'Verified buyer review',
    ]);

    expect($review->verified_purchase)->toBeTrue();
});

test('customer can only submit one review per product', function (): void {
    $product = Product::factory()->active()->create(['brand_id' => null]);
    $customer = Customer::factory()->create();
    $service = app(ProductReviewService::class);

    $service->customerStore($customer, $product, [
        'rating' => 4,
        'content' => 'First review',
    ]);

    expect(fn () => $service->customerStore($customer, $product, [
        'rating' => 5,
        'content' => 'Duplicate review',
    ]))->toThrow(ValidationException::class);

    expect(ProductReview::query()->where('customer_id', $customer->id)->where('product_id', $product->id)->count())->toBe(1);
});
