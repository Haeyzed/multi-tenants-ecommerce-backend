<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Commerce\ReturnInspectionStatus;
use App\Enums\Tenant\Commerce\ReturnItemCondition;
use App\Enums\Tenant\Commerce\ReturnStatus;
use App\Events\OrderCreated;
use App\Events\OrderReturnRequested;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Commerce\OrderReturnService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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
        '2026_08_15_060009_create_shipments_table.php',
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
        '2026_08_15_090001_create_order_returns_tables.php',
    ] as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    app(CommerceSettingService::class)->set('returns.window_days', '30');
    config(['notifications.sms.default' => 'null', 'notifications.sms.enabled' => false, 'notifications.queue' => false]);
});

/**
 * @return array{customer: Customer, order: Order, item: OrderItem, inventoryQty: int}
 */
function returnEligibleOrderFixture(): array
{
    Event::fake([OrderCreated::class, OrderReturnRequested::class]);

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

    app(CartService::class)->addItem($customer, $product->id, null, 2);
    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
    ]);

    $order->payment_status = OrderPaymentStatus::Paid;
    $order->status = OrderStatus::Fulfilled;
    $order->confirmed_at = now()->subDays(1);
    $order->save();

    OrderPayment::query()->create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'amount' => $order->grand_total,
        'currency' => $order->currency,
        'gateway' => 'paystack',
        'reference' => 'ORDPAY-TEST-'.$order->id,
        'provider_transaction_id' => 'txn_test',
        'status' => 'successful',
        'paid_at' => now(),
    ]);

    return [
        'customer' => $customer,
        'order' => $order->fresh(['items']) ?? $order,
        'item' => $order->items()->firstOrFail(),
        'inventoryQty' => (int) $inventory->fresh()->quantity,
    ];
}

test('customer can request return for fulfilled paid order', function (): void {
    $fixture = returnEligibleOrderFixture();
    $service = app(OrderReturnService::class);

    $return = $service->request($fixture['customer'], $fixture['order'], [
        'reason' => 'changed_mind',
        'items' => [
            ['order_item_id' => $fixture['item']->id, 'quantity' => 1],
        ],
    ]);

    expect($return->status)->toBe(ReturnStatus::Requested)
        ->and($return->items)->toHaveCount(1)
        ->and($return->return_number)->toStartWith('RET-');

    Event::assertDispatched(OrderReturnRequested::class);
});

test('return rejects quantity above returnable', function (): void {
    $fixture = returnEligibleOrderFixture();

    expect(fn () => app(OrderReturnService::class)->request($fixture['customer'], $fixture['order'], [
        'items' => [
            ['order_item_id' => $fixture['item']->id, 'quantity' => 99],
        ],
    ]))->toThrow(ValidationException::class);
});

test('return approval receive inspect and restock flow', function (): void {
    $fixture = returnEligibleOrderFixture();
    $service = app(OrderReturnService::class);
    $return = $service->request($fixture['customer'], $fixture['order'], [
        'items' => [
            ['order_item_id' => $fixture['item']->id, 'quantity' => 1],
        ],
    ]);

    $return = $service->markUnderReview($return);
    $return = $service->approve($return);
    expect($return->status)->toBe(ReturnStatus::AwaitingReturn);

    $return = $service->markReceived($return);
    $return = $service->startInspection($return);

    $inspector = User::factory()->create();
    $line = $return->items()->firstOrFail();
    $service->inspectItem($line, $inspector, [
        'inspection_status' => ReturnInspectionStatus::Accepted->value,
        'condition' => ReturnItemCondition::New->value,
        'restock' => true,
    ]);

    expect($line->fresh()->restocked)->toBeTrue();

    $return = $service->approveForRefund($return->fresh() ?? $return);
    expect($return->status)->toBe(ReturnStatus::ApprovedForRefund);
});
