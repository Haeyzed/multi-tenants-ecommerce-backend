<?php

declare(strict_types=1);

use App\DTO\Payment\PaymentRefundResult;
use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Warehouse;
use App\Services\Payment\Gateways\PaystackGateway;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\OrderPaymentService;
use App\Services\Tenant\Commerce\RefundService;
use App\Services\Tenant\Inventory\InventoryService;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        '2026_08_15_100201_create_gift_cards_table.php',
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
 * @return array{customer: Customer, payment: OrderPayment}
 */
function concurrencyPaidOrderFixture(): array
{
    Event::fake([OrderCreated::class, OrderPaid::class]);

    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->default()->create();
    $product = Product::factory()->active()->create(['allow_backorder' => false]);
    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => '100.00',
        'is_active' => true,
    ]);
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $product);
    app(InventoryService::class)->adjust($inventory, 5, InventoryMovementType::OpeningStock, 'Opening');

    app(CartService::class)->addItem($customer, $product->id, null, 2);
    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
    ]);

    $payment = OrderPayment::query()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'amount' => $order->grand_total,
        'currency' => $order->currency,
        'gateway' => 'paystack',
        'reference' => 'REF-CONC-1',
        'provider_transaction_id' => '1234567890',
        'status' => OrderPaymentRecordStatus::Pending,
    ]);

    app(OrderPaymentService::class)->markSuccessful($payment, '1234567890');

    return [
        'customer' => $customer,
        'payment' => $payment->fresh(['order']) ?? $payment,
    ];
}

test('in-flight processing refunds reduce the refundable balance', function (): void {
    $fixture = concurrencyPaidOrderFixture();
    $payment = $fixture['payment'];
    $order = $payment->order;

    Refund::query()->create([
        'order_id' => $order->id,
        'order_payment_id' => $payment->id,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'reference' => 'REF-INFLIGHT-1',
        'status' => RefundStatus::Processing,
    ]);

    expect(fn () => app(RefundService::class)->create($order, $payment, [
        'amount' => (string) $payment->amount,
    ]))->toThrow(ValidationException::class);
});

test('duplicate successful payments do not post a second sale journal', function (): void {
    $fixture = concurrencyPaidOrderFixture();
    $order = $fixture['payment']->order;

    $duplicate = OrderPayment::query()->create([
        'order_id' => $order->id,
        'customer_id' => $fixture['customer']->id,
        'amount' => $order->grand_total,
        'currency' => $order->currency,
        'gateway' => 'paystack',
        'reference' => 'REF-CONC-2',
        'provider_transaction_id' => '9999999999',
        'status' => OrderPaymentRecordStatus::Pending,
    ]);

    $result = app(OrderPaymentService::class)->markSuccessful($duplicate, '9999999999');

    expect($result->status)->toBe(OrderPaymentRecordStatus::Cancelled)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and(JournalEntry::query()->where('entry_type', 'sale')->count())->toBe(1);
});

test('refund gateway is not rolled back when ledger commit succeeds after provider success', function (): void {
    $fixture = concurrencyPaidOrderFixture();
    $order = $fixture['payment']->order;

    $mock = Mockery::mock(PaystackGateway::class);
    $mock->shouldReceive('refundPaymentDetailed')
        ->once()
        ->andReturn(new PaymentRefundResult(
            successful: true,
            providerRefundId: 'rf_concurrency',
            amount: (string) $fixture['payment']->amount,
            currency: 'NGN',
        ));
    app()->instance(PaystackGateway::class, $mock);

    $refund = app(RefundService::class)->create($order, $fixture['payment'], [
        'reason' => 'Customer request',
    ]);

    expect($refund->status)->toBe(RefundStatus::Completed)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Refunded);
});

test('gift cards remain queryable after deleted_at is set because soft deletes are disabled', function (): void {
    $card = GiftCard::factory()->create();

    DB::table('gift_cards')
        ->where('id', $card->id)
        ->update(['deleted_at' => now()]);

    expect(GiftCard::query()->find($card->id))->not->toBeNull();
});
