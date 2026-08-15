<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Enums\Tenant\Loyalty\LoyaltyAccountStatus;
use App\Enums\Tenant\Loyalty\LoyaltyTransactionType;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\RefundCompleted;
use App\Listeners\Loyalty\EarnLoyaltyPointsForPaidOrder;
use App\Listeners\Loyalty\ReverseLoyaltyPointsForRefund;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Loyalty\LoyaltyService;
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

    foreach (loyaltyMigrationFiles() as $file) {
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
function loyaltyMigrationFiles(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_031731_create_categories_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034307_create_category_product_table.php',
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
        '2026_08_15_070003_create_seller_offers_table.php',
        '2026_08_15_070004_add_seller_offer_to_cart_and_order_items.php',
        '2026_08_15_080001_create_taxes_table.php',
        '2026_08_15_080002_create_tax_rates_table.php',
        '2026_08_15_080003_create_tax_zones_table.php',
        '2026_08_15_080004_create_tax_zone_locations_table.php',
        '2026_08_15_080005_create_tax_rules_table.php',
        '2026_08_15_080006_add_tax_snapshot_to_orders_table.php',
        '2026_08_15_080009_create_refunds_table.php',
        '2026_08_15_090101_create_coupons_table.php',
        '2026_08_15_090108_add_coupon_and_promotion_fields_to_orders_table.php',
        '2026_08_15_100101_create_loyalty_programs_table.php',
        '2026_08_15_100102_create_loyalty_accounts_table.php',
        '2026_08_15_100103_create_loyalty_transactions_table.php',
        '2026_08_15_100104_add_loyalty_points_to_orders_table.php',
    ];
}

/**
 * @return array{customer: Customer, address: CustomerAddress, product: Product}
 */
function loyaltyFixture(int $stock = 10, string $price = '100.00'): array
{
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
    app(InventoryService::class)->adjust($inventory, $stock, InventoryMovementType::OpeningStock, 'Opening');

    return [
        'customer' => $customer,
        'address' => $address,
        'product' => $product,
    ];
}

/**
 * Check out a paid order worth two units of the fixture product.
 *
 * @param  array{customer: Customer, address: CustomerAddress, product: Product}  $fixture
 */
function loyaltyPaidOrder(array $fixture, int $quantity = 2): Order
{
    app(CartService::class)->addItem($fixture['customer'], $fixture['product']->id, null, $quantity);

    $order = app(CheckoutService::class)->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
    ]);

    $order->payment_status = OrderPaymentStatus::Paid;
    $order->status = OrderStatus::Confirmed;
    $order->confirmed_at = now();
    $order->save();

    return $order->fresh(['items', 'customer']) ?? $order;
}

test('a loyalty account is created once per customer with a zero balance', function (): void {
    $service = app(LoyaltyService::class);
    $customer = Customer::factory()->create();

    $account = $service->getOrCreateAccount($customer);

    expect($account->balance)->toBe(0)
        ->and($account->lifetime_earned)->toBe(0)
        ->and($account->status)->toBe(LoyaltyAccountStatus::Active)
        ->and($service->getOrCreateAccount($customer)->id)->toBe($account->id);
});

test('the program row is created from commerce settings defaults', function (): void {
    $program = app(LoyaltyService::class)->ensureProgram();

    expect($program->points_per_currency_unit)->toBe('1.00')
        ->and($program->redemption_points_per_currency)->toBe(100)
        ->and($program->min_redemption_points)->toBe(100)
        ->and($program->is_active)->toBeTrue()
        ->and(app(LoyaltyService::class)->ensureProgram()->id)->toBe($program->id);
});

test('a paid order earns points through the order paid listener', function (): void {
    Event::fake([OrderCreated::class]);

    $service = app(LoyaltyService::class);
    $service->ensureProgram();

    $fixture = loyaltyFixture();
    $order = loyaltyPaidOrder($fixture);

    app(EarnLoyaltyPointsForPaidOrder::class)->handle(new OrderPaid($order));

    $account = $service->getOrCreateAccount($fixture['customer']);

    expect($order->subtotal)->toBe('200.00')
        ->and($account->balance)->toBe(200)
        ->and($account->lifetime_earned)->toBe(200)
        ->and($order->fresh()->loyalty_points_earned)->toBe(200)
        ->and(LoyaltyTransaction::query()->where('type', LoyaltyTransactionType::Earn)->count())->toBe(1);

    app(EarnLoyaltyPointsForPaidOrder::class)->handle(new OrderPaid($order->fresh(['customer'])));

    expect($service->getOrCreateAccount($fixture['customer'])->balance)->toBe(200)
        ->and(LoyaltyTransaction::query()->where('type', LoyaltyTransactionType::Earn)->count())->toBe(1);
});

test('redeeming more points than the balance is rejected', function (): void {
    $service = app(LoyaltyService::class);
    $service->ensureProgram();

    $customer = Customer::factory()->create();
    $service->getOrCreateAccount($customer);

    expect(fn () => $service->redeemForCheckout($customer, 500, '100.00'))
        ->toThrow(ValidationException::class);

    expect($service->getOrCreateAccount($customer)->balance)->toBe(0)
        ->and(LoyaltyTransaction::query()->count())->toBe(0);
});

test('redeeming below the program minimum is rejected', function (): void {
    $service = app(LoyaltyService::class);
    $service->ensureProgram();

    $customer = Customer::factory()->create();
    $service->adjust($service->getOrCreateAccount($customer), 1000, 'Seed');

    expect(fn () => $service->redeemForCheckout($customer, 50, '100.00'))
        ->toThrow(ValidationException::class);
});

test('points redeemed at checkout discount the order before tax', function (): void {
    Event::fake([OrderCreated::class]);

    $service = app(LoyaltyService::class);
    $service->ensureProgram();

    $fixture = loyaltyFixture();
    $account = $service->getOrCreateAccount($fixture['customer']);
    $service->adjust($account, 1000, 'Seed');

    app(CartService::class)->addItem($fixture['customer'], $fixture['product']->id, null, 2);

    $order = app(CheckoutService::class)->checkout($fixture['customer'], [
        'shipping_address_id' => $fixture['address']->id,
        'loyalty_points' => 500,
    ]);

    $account = $account->fresh();

    expect($order->subtotal)->toBe('200.00')
        ->and($order->discount_total)->toBe('5.00')
        ->and($order->grand_total)->toBe('195.00')
        ->and($order->loyalty_points_redeemed)->toBe(500)
        ->and($order->items->first()->discount_amount)->toBe('5.00')
        ->and($account->balance)->toBe(500)
        ->and($account->lifetime_redeemed)->toBe(500);
});

test('a completed refund reverses a proportional share of earned points', function (): void {
    Event::fake([OrderCreated::class]);

    $service = app(LoyaltyService::class);
    $service->ensureProgram();

    $fixture = loyaltyFixture();
    $order = loyaltyPaidOrder($fixture);

    app(EarnLoyaltyPointsForPaidOrder::class)->handle(new OrderPaid($order));

    $payment = OrderPayment::query()->create([
        'order_id' => $order->id,
        'customer_id' => $fixture['customer']->id,
        'amount' => $order->grand_total,
        'currency' => $order->currency,
        'gateway' => 'paystack',
        'reference' => 'ORDPAY-LOYALTY-'.$order->id,
        'provider_transaction_id' => 'txn_loyalty',
        'status' => 'successful',
        'paid_at' => now(),
    ]);

    $refund = Refund::query()->create([
        'order_id' => $order->id,
        'order_payment_id' => $payment->id,
        'amount' => '100.00',
        'currency' => $order->currency,
        'reference' => 'REF-LOYALTY-'.$order->id,
        'status' => RefundStatus::Completed,
        'processed_at' => now(),
    ]);

    app(ReverseLoyaltyPointsForRefund::class)->handle(new RefundCompleted($refund));

    $account = $service->getOrCreateAccount($fixture['customer']);
    $reversal = LoyaltyTransaction::query()
        ->where('type', LoyaltyTransactionType::RefundReversal)
        ->firstOrFail();

    expect($account->balance)->toBe(100)
        ->and($reversal->points)->toBe(-100)
        ->and($reversal->balance_after)->toBe(100)
        ->and(LoyaltyTransaction::query()->where('type', LoyaltyTransactionType::Earn)->firstOrFail()->points)->toBe(200);

    app(ReverseLoyaltyPointsForRefund::class)->handle(new RefundCompleted($refund->fresh(['order.customer'])));

    expect($service->getOrCreateAccount($fixture['customer'])->balance)->toBe(100)
        ->and(LoyaltyTransaction::query()->where('type', LoyaltyTransactionType::RefundReversal)->count())->toBe(1);
});

test('loyalty balances are isolated between customers', function (): void {
    Event::fake([OrderCreated::class]);

    $service = app(LoyaltyService::class);
    $service->ensureProgram();

    $fixture = loyaltyFixture();
    $order = loyaltyPaidOrder($fixture);
    app(EarnLoyaltyPointsForPaidOrder::class)->handle(new OrderPaid($order));

    $otherCustomer = Customer::factory()->create();
    $otherAccount = $service->getOrCreateAccount($otherCustomer);

    expect($service->getOrCreateAccount($fixture['customer'])->balance)->toBe(200)
        ->and($otherAccount->balance)->toBe(0)
        ->and($otherAccount->transactions()->count())->toBe(0);
});

test('program settings can be updated', function (): void {
    $service = app(LoyaltyService::class);
    $service->ensureProgram();

    $program = $service->updateProgram([
        'points_per_currency_unit' => '2.00',
        'redemption_points_per_currency' => 50,
        'min_redemption_points' => 10,
        'earn_on_order_paid' => false,
    ]);

    expect($program->points_per_currency_unit)->toBe('2.00')
        ->and($program->redemption_points_per_currency)->toBe(50)
        ->and($program->min_redemption_points)->toBe(10)
        ->and($program->earn_on_order_paid)->toBeFalse();
});
