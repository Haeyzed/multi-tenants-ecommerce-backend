<?php

declare(strict_types=1);

use App\DTO\Payment\PaymentRefundResult;
use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Http\Requests\Tenant\Commerce\StoreGiftCardRequest;
use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\MarkAbandonedCartsJob;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Warehouse;
use App\Rules\MoneyAmount;
use App\Services\Notification\NotificationService;
use App\Services\Payment\Gateways\PaystackGateway;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\CommerceAnalyticsService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Commerce\GiftCardService;
use App\Services\Tenant\Commerce\RefundService;
use App\Services\Tenant\Inventory\InventoryService;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Throwable;

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

test('money amount rule rejects scientific notation and excess decimals', function (): void {
    $rule = new MoneyAmount;

    expect(Validator::make(['amount' => '1e2'], ['amount' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['amount' => '10.001'], ['amount' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['amount' => '10.50'], ['amount' => [$rule]])->passes())->toBeTrue()
        ->and(Validator::make(['amount' => '0'], ['amount' => [$rule]])->fails())->toBeTrue()
        ->and(Validator::make(['amount' => '0'], ['amount' => [new MoneyAmount(allowZero: true)]])->passes())->toBeTrue();

    $requestRules = (new StoreGiftCardRequest)->rules();
    expect(Validator::make(['amount' => '1e2', 'activate' => true], $requestRules)->fails())->toBeTrue();
});

test('partial prepaid refunds can restore the remaining balance in a later call', function (): void {
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
    app(InventoryService::class)->adjust($inventory, 10, InventoryMovementType::OpeningStock, 'Opening');

    [$giftCard, $plainCode] = app(GiftCardService::class)->create([
        'amount' => '200.00',
        'currency' => 'NGN',
        'activate' => true,
    ]);

    app(CartService::class)->addItem($customer, $product->id, null, 2);
    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
        'gift_card_code' => $plainCode,
    ]);

    expect($giftCard->fresh()->balance)->toBe('0.00');

    $first = app(RefundService::class)->createPrepaid($order, ['amount' => '50.00']);
    expect($first->status)->toBe(RefundStatus::Completed)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::PartiallyRefunded)
        ->and($giftCard->fresh()->balance)->toBe('50.00');

    $second = app(RefundService::class)->createPrepaid($order->fresh() ?? $order, ['amount' => '150.00']);
    expect($second->status)->toBe(RefundStatus::Completed)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Refunded)
        ->and($giftCard->fresh()->balance)->toBe('200.00');
});

test('tenant commerce jobs exit safely when tenant id is missing', function (): void {
    expect(fn () => (new MarkAbandonedCartsJob(null))->handle(
        app(CommerceSettingService::class),
        app(NotificationService::class),
        app(CommerceAnalyticsService::class),
    ))->not->toThrow(Throwable::class);

    expect(fn () => (new GenerateInvoicePdfJob(1, null))->handle())
        ->not->toThrow(Throwable::class);
});

test('refundAllocated draws gateway balance before prepaid remainder', function (): void {
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
    app(InventoryService::class)->adjust($inventory, 10, InventoryMovementType::OpeningStock, 'Opening');

    [$giftCard, $plainCode] = app(GiftCardService::class)->create([
        'amount' => '50.00',
        'currency' => 'NGN',
        'activate' => true,
    ]);

    app(CartService::class)->addItem($customer, $product->id, null, 2);
    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
        'gift_card_code' => $plainCode,
    ]);

    expect($order->grand_total)->toBe('150.00')
        ->and($order->gift_card_amount)->toBe('50.00');

    OrderPayment::query()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'amount' => $order->grand_total,
        'currency' => $order->currency,
        'gateway' => 'paystack',
        'reference' => 'ORDPAY-MIX-'.$order->id,
        'provider_transaction_id' => 'txn_mix',
        'status' => OrderPaymentRecordStatus::Successful,
        'paid_at' => now(),
    ]);

    $mock = Mockery::mock(PaystackGateway::class);
    $mock->shouldReceive('refundPaymentDetailed')
        ->once()
        ->withArgs(fn ($txn, $amount): bool => $txn === 'txn_mix' && $amount === '150.00')
        ->andReturn(new PaymentRefundResult(
            successful: true,
            providerRefundId: 'rf_mix',
            amount: '150.00',
            currency: 'NGN',
        ));
    app()->instance(PaystackGateway::class, $mock);

    $refund = app(RefundService::class)->refundAllocated($order, '200.00', [
        'reason' => 'Full mixed return',
    ]);

    expect($refund->status)->toBe(RefundStatus::Completed)
        ->and($giftCard->fresh()->balance)->toBe('50.00')
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Refunded)
        ->and(Refund::query()->where('order_id', $order->id)->count())->toBe(2);
});
