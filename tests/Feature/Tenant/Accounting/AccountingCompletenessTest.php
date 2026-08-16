<?php

declare(strict_types=1);

use App\Enums\Tenant\Accounting\JournalEntryStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Seller;
use App\Services\Tenant\Accounting\AccountingReportService;
use App\Services\Tenant\Accounting\AccountingService;
use App\Services\Tenant\Accounting\JournalEntryService;
use App\Support\Money;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);

    foreach (accountingCompletenessMigrations() as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed(ChartOfAccountsSeeder::class);
});

/**
 * @return list<string>
 */
function accountingCompletenessMigrations(): array
{
    return [
        '2026_08_15_031728_create_brands_table.php',
        '2026_08_15_034243_create_units_table.php',
        '2026_08_15_034246_create_warehouses_table.php',
        '2026_08_15_034249_create_warehouse_locations_table.php',
        '2026_08_15_034302_create_products_table.php',
        '2026_08_15_034305_create_product_variants_table.php',
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034321_create_inventory_movements_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060003_create_carts_table.php',
        '2026_08_15_060004_create_cart_items_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_060008_create_order_payments_table.php',
        '2026_08_15_060016_create_accounts_table.php',
        '2026_08_15_060017_create_journal_entries_table.php',
        '2026_08_15_060018_create_journal_entry_lines_table.php',
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_15_070003_create_seller_offers_table.php',
        '2026_08_15_070004_add_seller_offer_to_cart_and_order_items.php',
        '2026_08_15_070005_create_seller_orders_tables.php',
        '2026_08_15_070006_create_seller_commissions_table.php',
        '2026_08_15_080009_create_refunds_table.php',
        '2026_08_15_100201_create_gift_cards_table.php',
        '2026_08_15_100203_add_gift_card_fields_to_orders_table.php',
    ];
}

test('partial refund with shipping posts a balanced journal', function (): void {
    $order = Order::factory()->create([
        'subtotal' => '100.00',
        'discount_total' => '0.00',
        'tax_total' => '10.00',
        'shipping_total' => '15.00',
        'grand_total' => '125.00',
    ]);

    $refund = Refund::query()->create([
        'order_id' => $order->id,
        'order_payment_id' => null,
        'amount' => '25.00',
        'currency' => 'USD',
        'reference' => 'REF-PARTIAL-1',
        'status' => RefundStatus::Completed,
        'processed_at' => now(),
    ]);

    $entry = app(AccountingService::class)->postPartialRefund($order, '25.00', $refund);

    expect($entry)->toBeInstanceOf(JournalEntry::class)
        ->and($entry->status)->toBe(JournalEntryStatus::Posted);

    $debit = '0.00';
    $credit = '0.00';
    foreach ($entry->lines as $line) {
        $debit = Money::add($debit, (string) $line->debit);
        $credit = Money::add($credit, (string) $line->credit);
    }

    expect($debit)->toBe($credit)
        ->and($credit)->toBe('25.00');

    $sales = Account::query()->where('code', '4000')->firstOrFail();
    $tax = Account::query()->where('code', '2100')->firstOrFail();
    $cash = Account::query()->where('code', '1000')->firstOrFail();

    expect((string) $entry->lines->firstWhere('account_id', $cash->id)?->credit)->toBe('25.00');

    $salesDebit = $entry->lines->where('account_id', $sales->id)->sum(fn ($l) => (float) $l->debit);
    $taxDebit = (float) ($entry->lines->firstWhere('account_id', $tax->id)?->debit ?? 0);

    // 100/125 * 25 = 20 sales, 10/125 * 25 = 2 tax, 15/125 * 25 = 3 shipping → all on sales+tax accounts
    expect(round($salesDebit + $taxDebit, 2))->toBe(25.0)
        ->and($taxDebit)->toBe(2.0)
        ->and(round($salesDebit, 2))->toBe(23.0);
});

test('marketplace sale with empty commissions balances via sales residual', function (): void {
    $seller = Seller::factory()->sellable()->create();
    $order = Order::factory()->create([
        'subtotal' => '100.00',
        'discount_total' => '0.00',
        'tax_total' => '5.00',
        'shipping_total' => '10.00',
        'grand_total' => '115.00',
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'seller_id' => $seller->id,
        'product_name' => 'Marketplace Item',
        'quantity' => 1,
        'unit_price' => '100.00',
        'discount_amount' => '0.00',
        'tax_amount' => '5.00',
        'subtotal' => '100.00',
        'total' => '105.00',
    ]);

    $entry = app(AccountingService::class)->postSale($order->fresh(['items']));

    expect($entry)->toBeInstanceOf(JournalEntry::class)
        ->and($entry->status)->toBe(JournalEntryStatus::Posted);

    $debit = '0.00';
    $credit = '0.00';
    foreach ($entry->lines as $line) {
        $debit = Money::add($debit, (string) $line->debit);
        $credit = Money::add($credit, (string) $line->credit);
    }

    expect($debit)->toBe($credit)->and($debit)->toBe('115.00');

    $sales = Account::query()->where('code', '4000')->firstOrFail();
    $residual = $entry->lines
        ->where('account_id', $sales->id)
        ->filter(fn ($line) => str_contains((string) $line->description, 'residual'))
        ->first();

    expect($residual)->not->toBeNull()
        ->and((string) $residual->credit)->toBe('100.00');
});

test('prepaid gift card sale debits liability not only cash', function (): void {
    if (! Schema::hasColumn('orders', 'gift_card_amount')) {
        $this->markTestSkipped('gift_card_amount column not present');
    }

    $giftLiability = Account::query()->where('code', '2300')->firstOrFail();
    $cash = Account::query()->where('code', '1000')->firstOrFail();

    $order = Order::factory()->create([
        'subtotal' => '100.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'shipping_total' => '0.00',
        'grand_total' => '60.00',
        'gift_card_amount' => '40.00',
    ]);

    $entry = app(AccountingService::class)->postSale($order);

    expect($entry)->toBeInstanceOf(JournalEntry::class);

    $cashLine = $entry->lines->firstWhere('account_id', $cash->id);
    $giftLine = $entry->lines->firstWhere('account_id', $giftLiability->id);

    expect((string) $cashLine?->debit)->toBe('60.00')
        ->and((string) $giftLine?->debit)->toBe('40.00');

    $debit = '0.00';
    $credit = '0.00';
    foreach ($entry->lines as $line) {
        $debit = Money::add($debit, (string) $line->debit);
        $credit = Money::add($credit, (string) $line->credit);
    }

    expect($debit)->toBe($credit)->and($debit)->toBe('100.00');
});

test('trial balance returns balanced totals across accounts', function (): void {
    $order = Order::factory()->create([
        'subtotal' => '80.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'shipping_total' => '0.00',
        'grand_total' => '80.00',
    ]);

    app(AccountingService::class)->postSale($order);

    $rows = app(AccountingReportService::class)->trialBalance();

    $totalDebit = '0.00';
    $totalCredit = '0.00';
    foreach ($rows as $row) {
        $totalDebit = Money::add($totalDebit, $row['debit']);
        $totalCredit = Money::add($totalCredit, $row['credit']);
    }

    expect($totalDebit)->toBe($totalCredit)
        ->and(bccomp($totalDebit, '0', 2))->toBe(1);
});

test('reverse posts a reversing journal entry', function (): void {
    $cash = Account::query()->where('code', '1000')->firstOrFail();
    $sales = Account::query()->where('code', '4000')->firstOrFail();
    $journals = app(JournalEntryService::class);

    $entry = $journals->createDraft(
        'JE-REV-SRC',
        'Source entry',
        now()->toDateString(),
        [
            ['account_id' => $cash->id, 'debit' => '50.00', 'credit' => '0'],
            ['account_id' => $sales->id, 'debit' => '0', 'credit' => '50.00'],
        ],
    );
    $posted = $journals->post($entry);

    $reversal = $journals->reverse($posted);

    expect($reversal->status)->toBe(JournalEntryStatus::Posted)
        ->and($reversal->entry_type)->toBe('reverse')
        ->and((string) $reversal->lines->firstWhere('account_id', $cash->id)?->credit)->toBe('50.00')
        ->and((string) $reversal->lines->firstWhere('account_id', $sales->id)?->debit)->toBe('50.00');
});
