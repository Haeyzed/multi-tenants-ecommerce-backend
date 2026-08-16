<?php

declare(strict_types=1);

use App\Enums\Tenant\Analytics\DateRangePreset;
use App\Enums\Tenant\Analytics\ReportInterval;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Enums\Tenant\Marketplace\CommissionType;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\CouponUsage;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Product;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerCommission;
use App\Models\Tenant\SellerOrder;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Analytics\AnalyticsCsvExporter;
use App\Services\Tenant\Analytics\CommerceReportService;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (commerceReportMigrationFiles() as $file) {
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
function commerceReportMigrationFiles(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_060008_create_order_payments_table.php',
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_16_160758_make_sellers_authenticatable_table.php',
        '2026_08_15_070005_create_seller_orders_tables.php',
        '2026_08_15_070006_create_seller_commissions_table.php',
        '2026_08_15_080009_create_refunds_table.php',
        '2026_08_15_090101_create_coupons_table.php',
        '2026_08_15_090102_create_coupon_usages_table.php',
    ];
}

/**
 * The window every assertion in this file reports over.
 */
function reportRange(): DateRange
{
    return DateRange::fromParams(['period' => DateRangePreset::Last30Days->value]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function reportableOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'status' => OrderStatus::Confirmed,
        'subtotal' => '100.00',
        'discount_total' => '10.00',
        'tax_total' => '5.00',
        'shipping_total' => '15.00',
        'grand_total' => '110.00',
        'placed_at' => now()->subDay(),
    ], $attributes));
}

test('sales summary aggregates money columns and ignores cancelled orders', function (): void {
    reportableOrder();
    reportableOrder();
    reportableOrder([
        'status' => OrderStatus::Cancelled,
        'grand_total' => '999.00',
        'cancelled_at' => now(),
    ]);
    reportableOrder(['placed_at' => now()->subMonths(6)]);

    $summary = app(CommerceReportService::class)->salesSummary(reportRange());

    expect($summary['orders_count'])->toBe(2)
        ->and($summary['gross'])->toBe('200.00')
        ->and($summary['net'])->toBe('220.00')
        ->and($summary['aov'])->toBe('110.00')
        ->and($summary['discount_total'])->toBe('20.00')
        ->and($summary['tax_total'])->toBe('10.00')
        ->and($summary['shipping_total'])->toBe('30.00');
});

test('sales summary counts completed refunds and sold items', function (): void {
    $order = reportableOrder();

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => Product::factory()->create()->id,
        'product_name' => 'Widget',
        'quantity' => 4,
        'unit_price' => '25.00',
        'subtotal' => '100.00',
        'total' => '100.00',
    ]);

    /** @var OrderPayment $payment */
    $payment = OrderPayment::query()->create([
        'order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'amount' => '110.00',
        'currency' => 'USD',
        'gateway' => 'paystack',
        'reference' => 'PAY-1',
        'status' => OrderPaymentRecordStatus::Successful,
        'paid_at' => now(),
    ]);

    Refund::query()->create([
        'order_id' => $order->id,
        'order_payment_id' => $payment->id,
        'amount' => '40.00',
        'currency' => 'USD',
        'reference' => 'REF-1',
        'status' => RefundStatus::Completed,
        'processed_at' => now(),
    ]);

    Refund::query()->create([
        'order_id' => $order->id,
        'order_payment_id' => $payment->id,
        'amount' => '25.00',
        'currency' => 'USD',
        'reference' => 'REF-2',
        'status' => RefundStatus::Pending,
    ]);

    $summary = app(CommerceReportService::class)->salesSummary(reportRange());

    expect($summary['refund_total'])->toBe('40.00')
        ->and($summary['items_sold'])->toBe(4);
});

test('sales summary of an empty period returns zeroed money strings', function (): void {
    $summary = app(CommerceReportService::class)->salesSummary(reportRange());

    expect($summary['orders_count'])->toBe(0)
        ->and($summary['net'])->toBe('0.00')
        ->and($summary['aov'])->toBe('0.00')
        ->and($summary['refund_total'])->toBe('0.00');
});

test('sales breakdown groups orders into buckets', function (): void {
    reportableOrder(['placed_at' => now()->subDays(2)->setTime(9, 0)]);
    reportableOrder(['placed_at' => now()->subDays(2)->setTime(18, 0)]);
    reportableOrder(['placed_at' => now()->subDay()->setTime(10, 0)]);

    $service = app(CommerceReportService::class);

    $daily = $service->salesBreakdown(reportRange(), ReportInterval::Day);
    $monthly = $service->salesBreakdown(reportRange(), ReportInterval::Month);

    expect($daily)->toHaveCount(2)
        ->and($daily[0]['bucket'])->toBe(now()->subDays(2)->toDateString())
        ->and($daily[0]['orders_count'])->toBe(2)
        ->and($daily[0]['net'])->toBe('220.00')
        ->and(array_sum(array_column($monthly, 'orders_count')))->toBe(3);
});

test('customer metrics separate new active and returning customers', function (): void {
    $returning = Customer::factory()->create(['created_at' => now()->subYear()]);
    reportableOrder(['customer_id' => $returning->id, 'placed_at' => now()->subYear()]);
    reportableOrder(['customer_id' => $returning->id]);

    $newBuyer = Customer::factory()->create();
    reportableOrder(['customer_id' => $newBuyer->id]);

    Customer::factory()->create(['created_at' => now()->subYear()]);

    $metrics = app(CommerceReportService::class)->customerMetrics(reportRange());

    expect($metrics['total_customers'])->toBe(3)
        ->and($metrics['new_customers'])->toBe(1)
        ->and($metrics['active_customers'])->toBe(2)
        ->and($metrics['returning_customers'])->toBe(1)
        ->and($metrics['average_customer_value'])->toBe('110.00');
});

test('product metrics rank top sellers by quantity', function (): void {
    $order = reportableOrder();
    $bestSeller = Product::factory()->create(['name' => 'Best Seller']);
    $slowMover = Product::factory()->create(['name' => 'Slow Mover']);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $bestSeller->id,
        'product_name' => $bestSeller->name,
        'quantity' => 7,
        'unit_price' => '10.00',
        'subtotal' => '70.00',
        'total' => '70.00',
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $slowMover->id,
        'product_name' => $slowMover->name,
        'quantity' => 2,
        'unit_price' => '10.00',
        'subtotal' => '20.00',
        'total' => '20.00',
    ]);

    $rows = app(CommerceReportService::class)->productMetrics(reportRange());

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['product_id'])->toBe($bestSeller->id)
        ->and($rows[0]['quantity_sold'])->toBe(7)
        ->and($rows[0]['revenue'])->toBe('70.00')
        ->and($rows[0]['orders_count'])->toBe(1);
});

test('inventory metrics classify low and out of stock rows', function (): void {
    $warehouse = Warehouse::factory()->create();

    $stocked = fn (int $quantity, int $reserved, ?int $reorderLevel): Inventory => Inventory::query()->create([
        'warehouse_id' => $warehouse->id,
        'inventoryable_type' => Product::class,
        'inventoryable_id' => Product::factory()->create()->id,
        'quantity' => $quantity,
        'reserved_quantity' => $reserved,
        'reorder_level' => $reorderLevel,
    ]);

    $stocked(100, 10, 5);
    $stocked(8, 0, 10);
    $stocked(3, 3, 5);

    $metrics = app(CommerceReportService::class)->inventoryMetrics();

    expect($metrics['tracked_items'])->toBe(3)
        ->and($metrics['quantity_on_hand'])->toBe(111)
        ->and($metrics['quantity_reserved'])->toBe(13)
        ->and($metrics['quantity_available'])->toBe(98)
        ->and($metrics['low_stock_count'])->toBe(1)
        ->and($metrics['out_of_stock_count'])->toBe(1);
});

test('marketplace metrics aggregate commissions and scope to a seller', function (): void {
    $first = Seller::factory()->sellable()->create();
    $second = Seller::factory()->sellable()->create();

    $commission = function (Seller $seller, string $subtotal, string $commission, string $earnings): void {
        /** @var SellerOrder $sellerOrder */
        $sellerOrder = SellerOrder::query()->create([
            'order_id' => reportableOrder()->id,
            'seller_id' => $seller->id,
            'subtotal' => $subtotal,
            'seller_total' => $earnings,
            'commission_total' => $commission,
        ]);

        SellerCommission::query()->create([
            'seller_order_id' => $sellerOrder->id,
            'seller_id' => $seller->id,
            'order_id' => $sellerOrder->order_id,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => '10.0000',
            'order_subtotal' => $subtotal,
            'commission_amount' => $commission,
            'seller_amount' => $earnings,
        ]);
    };

    $commission($first, '200.00', '20.00', '180.00');
    $commission($second, '100.00', '10.00', '90.00');

    $service = app(CommerceReportService::class);

    $all = $service->marketplaceMetrics(reportRange());
    $scoped = $service->sellerMetrics(reportRange(), $first->id);

    expect($all)->not->toBeNull()
        ->and($all['sellers_count'])->toBe(2)
        ->and($all['gross_sales'])->toBe('300.00')
        ->and($all['commission_total'])->toBe('30.00')
        ->and($all['seller_earnings'])->toBe('270.00')
        ->and($scoped['sellers_count'])->toBe(1)
        ->and($scoped['gross_sales'])->toBe('200.00');
});

test('coupon metrics summarise redemptions and rank coupons', function (): void {
    $popular = Coupon::factory()->create();
    $quiet = Coupon::factory()->create();
    $customer = Customer::factory()->create();

    CouponUsage::query()->create([
        'coupon_id' => $popular->id,
        'customer_id' => $customer->id,
        'discount_amount' => '30.00',
    ]);

    CouponUsage::query()->create([
        'coupon_id' => $popular->id,
        'customer_id' => Customer::factory()->create()->id,
        'discount_amount' => '20.00',
    ]);

    CouponUsage::query()->create([
        'coupon_id' => $quiet->id,
        'customer_id' => $customer->id,
        'discount_amount' => '5.00',
    ]);

    $metrics = app(CommerceReportService::class)->couponMetrics(reportRange());

    expect($metrics['redemptions'])->toBe(3)
        ->and($metrics['discount_total'])->toBe('55.00')
        ->and($metrics['unique_customers'])->toBe(2)
        ->and($metrics['top_coupons'][0]['coupon_id'])->toBe($popular->id)
        ->and($metrics['top_coupons'][0]['discount_total'])->toBe('50.00');
});

test('payment metrics split captures by gateway and count failures', function (): void {
    $order = reportableOrder();

    $payment = function (string $gateway, string $amount, OrderPaymentRecordStatus $status, string $reference) use ($order): void {
        OrderPayment::query()->create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'amount' => $amount,
            'currency' => 'USD',
            'gateway' => $gateway,
            'reference' => $reference,
            'status' => $status,
        ]);
    };

    $payment('paystack', '100.00', OrderPaymentRecordStatus::Successful, 'PAY-1');
    $payment('paystack', '50.00', OrderPaymentRecordStatus::Successful, 'PAY-2');
    $payment('stripe', '30.00', OrderPaymentRecordStatus::Successful, 'PAY-3');
    $payment('stripe', '80.00', OrderPaymentRecordStatus::Failed, 'PAY-4');
    $payment('stripe', '20.00', OrderPaymentRecordStatus::Pending, 'PAY-5');

    $metrics = app(CommerceReportService::class)->paymentMetrics(reportRange());

    expect($metrics['captured_count'])->toBe(3)
        ->and($metrics['captured_total'])->toBe('180.00')
        ->and($metrics['failed_count'])->toBe(1)
        ->and($metrics['pending_count'])->toBe(1)
        ->and($metrics['by_gateway'][0])->toBe([
            'gateway' => 'paystack',
            'captured_count' => 2,
            'captured_total' => '150.00',
        ]);
});

test('date range presets resolve in the application timezone', function (): void {
    $today = DateRange::fromParams(['period' => DateRangePreset::Today->value]);
    $custom = DateRange::fromParams([
        'period' => DateRangePreset::Custom->value,
        'from' => '2026-03-10',
        'to' => '2026-03-12',
    ]);
    $reversed = DateRange::fromParams([
        'period' => DateRangePreset::Custom->value,
        'from' => '2026-03-12',
        'to' => '2026-03-10',
    ]);

    expect($today->from->toDateTimeString())->toBe(now()->startOfDay()->toDateTimeString())
        ->and($custom->bounds())->toBe(['2026-03-10 00:00:00', '2026-03-12 23:59:59'])
        ->and($reversed->bounds())->toBe($custom->bounds())
        ->and(DateRange::fromParams([])->preset)->toBe(DateRangePreset::Last30Days)
        ->and($today->toArray()['timezone'])->toBe(config('app.timezone'));
});

test('csv exporter streams summary rows', function (): void {
    $csv = app(AnalyticsCsvExporter::class)->summary('sales-summary.csv', [
        'orders_count' => 2,
        'net' => '220.00',
    ]);

    ob_start();
    $csv->sendContent();
    $content = (string) ob_get_clean();

    expect($csv->headers->get('Content-Type'))->toContain('text/csv')
        ->and($content)->toContain('metric,value')
        ->and($content)->toContain('orders_count,2')
        ->and($content)->toContain('net,220.00');
});
