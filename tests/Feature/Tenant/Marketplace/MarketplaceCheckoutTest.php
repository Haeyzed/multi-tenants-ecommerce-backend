<?php

declare(strict_types=1);

use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Events\OrderCreated;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\Product;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerOffer;
use App\Models\Tenant\SellerOrder;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Marketplace\SellerOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrationFiles = [
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
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060003_create_carts_table.php',
        '2026_08_15_060004_create_cart_items_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_060007_create_checkout_sessions_table.php',
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_15_070003_create_seller_offers_table.php',
        '2026_08_15_070004_add_seller_offer_to_cart_and_order_items.php',
        '2026_08_15_070005_create_seller_orders_tables.php',
        '2026_08_15_080001_create_taxes_table.php',
        '2026_08_15_080002_create_tax_rates_table.php',
        '2026_08_15_080003_create_tax_zones_table.php',
        '2026_08_15_080004_create_tax_zone_locations_table.php',
        '2026_08_15_080005_create_tax_rules_table.php',
        '2026_08_15_080006_add_tax_snapshot_to_orders_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    app(CommerceSettingService::class)->setMarketplaceEnabled(true);
});

/**
 * @return array{customer: Customer, address: CustomerAddress, offer: SellerOffer, seller: Seller}
 */
function marketplaceCheckoutFixture(int $stock = 10, string $price = '75.00'): array
{
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->default()->create();
    $seller = Seller::factory()->sellable()->create();
    $product = Product::factory()->active()->create();
    Warehouse::factory()->create(['is_default' => true]);

    $offer = app(SellerOfferService::class)->store([
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'price' => $price,
        'stock' => $stock,
    ]);

    return [
        'customer' => $customer,
        'address' => $address,
        'offer' => $offer,
        'seller' => $seller,
    ];
}

test('marketplace cart requires seller offer id', function (): void {
    $fixture = marketplaceCheckoutFixture();
    $cartService = app(CartService::class);

    expect(fn () => $cartService->addItem(
        $fixture['customer'],
        $fixture['offer']->product_id,
        null,
        1,
    ))->toThrow(ValidationException::class);
});

test('marketplace add item uses offer price and reserves offer stock at checkout', function (): void {
    Event::fake([OrderCreated::class]);

    $fixture = marketplaceCheckoutFixture(stock: 8, price: '75.00');
    $cartService = app(CartService::class);
    $checkoutService = app(CheckoutService::class);

    $item = $cartService->addItem(
        $fixture['customer'],
        $fixture['offer']->product_id,
        null,
        2,
        $fixture['offer']->id,
    );

    expect($item->seller_offer_id)->toBe($fixture['offer']->id)
        ->and($item->unit_price)->toBe('75.00')
        ->and($item->subtotal)->toBe('150.00');

    $order = $checkoutService->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    expect($order->subtotal)->toBe('150.00')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->seller_offer_id)->toBe($fixture['offer']->id)
        ->and($order->items->first()->seller_id)->toBe($fixture['seller']->id)
        ->and($order->items->first()->unit_price)->toBe('75.00');

    $sellerOrder = SellerOrder::query()->where('order_id', $order->id)->first();

    expect($sellerOrder)->not->toBeNull()
        ->and($sellerOrder->seller_id)->toBe($fixture['seller']->id)
        ->and($sellerOrder->status)->toBe(SellerOrderStatus::Pending)
        ->and($sellerOrder->subtotal)->toBe('150.00')
        ->and($sellerOrder->items)->toHaveCount(1);

    expect($fixture['offer']->inventories()->first()->fresh()->reserved_quantity)->toBe(2);
});

test('merging same offer in cart increments quantity', function (): void {
    $fixture = marketplaceCheckoutFixture();
    $cartService = app(CartService::class);

    $cartService->addItem($fixture['customer'], $fixture['offer']->product_id, null, 1, $fixture['offer']->id);
    $item = $cartService->addItem($fixture['customer'], $fixture['offer']->product_id, null, 2, $fixture['offer']->id);

    expect($item->quantity)->toBe(3)
        ->and($cartService->getCart($fixture['customer'])->items)->toHaveCount(1);
});

test('checkout rejects insufficient offer stock', function (): void {
    $fixture = marketplaceCheckoutFixture(stock: 1);
    $cartService = app(CartService::class);

    expect(fn () => $cartService->addItem(
        $fixture['customer'],
        $fixture['offer']->product_id,
        null,
        2,
        $fixture['offer']->id,
    ))->toThrow(ValidationException::class);
});
