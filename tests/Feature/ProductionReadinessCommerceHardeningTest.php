<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\CouponType;
use App\Enums\Tenant\Commerce\GiftCardTransactionType;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\CouponUsage;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\GiftCardTransaction;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\CouponService;
use App\Services\Tenant\Commerce\GiftCardService;
use App\Services\Tenant\Commerce\RefundService;
use App\Services\Tenant\Inventory\InventoryService;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);

    foreach ([
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
        '2026_08_15_060008_create_order_payments_table.php',
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_16_160758_make_sellers_authenticatable_table.php',
        '2026_08_15_070003_create_seller_offers_table.php',
        '2026_08_15_070004_add_seller_offer_to_cart_and_order_items.php',
        '2026_08_15_080001_create_taxes_table.php',
        '2026_08_15_080002_create_tax_rates_table.php',
        '2026_08_15_080003_create_tax_zones_table.php',
        '2026_08_15_080004_create_tax_zone_locations_table.php',
        '2026_08_15_080005_create_tax_rules_table.php',
        '2026_08_15_080006_add_tax_snapshot_to_orders_table.php',
        '2026_08_15_080009_create_refunds_table.php',
        '2026_08_15_060016_create_accounts_table.php',
        '2026_08_15_060017_create_journal_entries_table.php',
        '2026_08_15_060018_create_journal_entry_lines_table.php',
        '2026_08_15_090101_create_coupons_table.php',
        '2026_08_15_090102_create_coupon_usages_table.php',
        '2026_08_15_090103_create_coupon_product_table.php',
        '2026_08_15_090104_create_coupon_category_table.php',
        '2026_08_15_090108_add_coupon_and_promotion_fields_to_orders_table.php',
        '2026_08_15_100201_create_gift_cards_table.php',
        '2026_08_15_100202_create_gift_card_transactions_table.php',
        '2026_08_15_100203_add_gift_card_fields_to_orders_table.php',
    ] as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed(ChartOfAccountsSeeder::class);
});

/**
 * @return array{customer: Customer, address: CustomerAddress, product: Product}
 */
function commerceHardeningFixture(string $price = '100.00'): array
{
    Event::fake([OrderCreated::class, OrderPaid::class]);

    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->default()->create();
    $product = Product::factory()->active()->create(['allow_backorder' => false]);
    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => $price,
        'is_active' => true,
    ]);
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $product);
    app(InventoryService::class)->adjust($inventory, 20, InventoryMovementType::OpeningStock, 'Opening');

    return compact('customer', 'address', 'product');
}

test('coupon usage limit is re-checked under lock before recording usage', function (): void {
    $fixture = commerceHardeningFixture('100.00');

    $coupon = app(CouponService::class)->store([
        'name' => 'Once',
        'code' => 'ONCEONLY',
        'type' => CouponType::Percentage->value,
        'value' => '10',
        'usage_limit' => 1,
        'is_active' => true,
    ]);

    app(CartService::class)->addItem($fixture['customer'], $fixture['product']->id, null, 1);
    $first = app(CheckoutService::class)->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'coupon_code' => 'ONCEONLY',
    ]);

    expect(CouponUsage::query()->where('coupon_id', $coupon->id)->count())->toBe(1)
        ->and($first->coupon_code)->toBe('ONCEONLY');

    Coupon::query()->whereKey($coupon->id)->update(['usage_limit' => 1]);

    app(CartService::class)->addItem($fixture['customer'], $fixture['product']->id, null, 1);

    expect(fn () => app(CheckoutService::class)->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'coupon_code' => 'ONCEONLY',
    ]))->toThrow(ValidationException::class);
});

test('checkout allocates unique order numbers when a collision is forced', function (): void {
    $fixture = commerceHardeningFixture();

    $prefix = 'ORD-'.now()->format('Ymd').'-';
    Order::factory()->create([
        'customer_id' => $fixture['customer']->id,
        'order_number' => $prefix.'000001',
    ]);

    app(CartService::class)->addItem($fixture['customer'], $fixture['product']->id, null, 1);
    $order = app(CheckoutService::class)->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    expect($order->order_number)->toBe($prefix.'000002');
});

test('prepaid-only orders can be refunded without a gateway payment', function (): void {
    $fixture = commerceHardeningFixture('50.00');

    [$giftCard, $plainCode] = app(GiftCardService::class)->create([
        'amount' => '200.00',
        'currency' => 'NGN',
        'activate' => true,
    ]);

    app(CartService::class)->addItem($fixture['customer'], $fixture['product']->id, null, 2);
    $order = app(CheckoutService::class)->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'gift_card_code' => $plainCode,
    ]);

    expect($order->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and(bccomp((string) $order->grand_total, '0', 2))->toBe(0)
        ->and($giftCard->fresh()->balance)->toBe('100.00');

    $refund = app(RefundService::class)->createPrepaid($order, [
        'reason' => 'Customer cancelled',
    ]);

    expect($refund->status)->toBe(RefundStatus::Completed)
        ->and($refund->order_payment_id)->toBeNull()
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Refunded)
        ->and($giftCard->fresh()->balance)->toBe('200.00')
        ->and(GiftCardTransaction::query()
            ->where('gift_card_id', $giftCard->id)
            ->where('type', GiftCardTransactionType::RefundRestore)
            ->exists())->toBeTrue();
});
