<?php

declare(strict_types=1);

use App\Enums\Tenant\Accounting\JournalEntryStatus;
use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Enums\Tenant\Procurement\PurchaseOrderStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ShippingMethod;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Accounting\JournalEntryService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Procurement\GoodsReceiptService;
use App\Services\Tenant\Procurement\PurchaseOrderService;
use App\Services\Tenant\Procurement\SupplierService;
use App\Services\Tenant\Shipping\ShipmentService;
use App\Services\Tenant\Shipping\ShippingMethodService;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        '2026_08_15_034318_create_inventories_table.php',
        '2026_08_15_034321_create_inventory_movements_table.php',
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_060009_create_shipments_table.php',
        '2026_08_15_060010_create_suppliers_table.php',
        '2026_08_15_060011_create_supplier_contacts_table.php',
        '2026_08_15_060012_create_purchase_orders_table.php',
        '2026_08_15_060013_create_purchase_order_items_table.php',
        '2026_08_15_060014_create_goods_receipts_table.php',
        '2026_08_15_060015_create_goods_receipt_items_table.php',
        '2026_08_15_060016_create_accounts_table.php',
        '2026_08_15_060017_create_journal_entries_table.php',
        '2026_08_15_060018_create_journal_entry_lines_table.php',
    ];

    foreach ($migrationFiles as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed(ChartOfAccountsSeeder::class);
});

test('shipping method can be created and shipment transitioned', function (): void {
    $method = app(ShippingMethodService::class)->store([
        'name' => 'Standard',
        'code' => 'STD',
        'amount' => '15.00',
        'is_active' => true,
    ]);

    expect($method)->toBeInstanceOf(ShippingMethod::class)
        ->and($method->code)->toBe('STD');

    $order = Order::factory()->create(['shipping_method_id' => $method->id]);
    $shipment = app(ShipmentService::class)->create($order, [
        'shipping_method_id' => $method->id,
        'tracking_number' => 'TRK-1',
        'carrier' => 'DHL',
    ]);

    expect($shipment->status)->toBe(ShipmentStatus::Pending);

    $shipped = app(ShipmentService::class)->transition($shipment, ShipmentStatus::Shipped);

    expect($shipped->status)->toBe(ShipmentStatus::Shipped)
        ->and($shipped->shipped_at)->not->toBeNull();
});

test('supplier purchase order partial receive updates inventory and posts journal', function (): void {
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $product = Product::factory()->active()->create();
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $product);

    expect($inventory->quantity)->toBe(0);

    $supplier = app(SupplierService::class)->store([
        'name' => 'Acme Supplies',
        'code' => 'ACME',
    ]);

    expect($supplier)->toBeInstanceOf(Supplier::class);

    $poService = app(PurchaseOrderService::class);
    $po = $poService->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'currency' => 'NGN',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_cost' => '25.00',
            ],
        ],
    ]);

    $po = $poService->approve($po);
    $po = $poService->markOrdered($po);

    $item = $po->items->first();
    $receipt = app(GoodsReceiptService::class)->receive($po, [
        ['purchase_order_item_id' => $item->id, 'quantity' => 4],
    ]);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($item->fresh()->received_quantity)->toBe(4)
        ->and($inventory->fresh()->quantity)->toBe(4)
        ->and(JournalEntry::query()->where('entry_type', 'goods_receipt')->where('source_id', $receipt->id)->count())->toBe(1);

    $receipt2 = app(GoodsReceiptService::class)->receive($po->fresh(), [
        ['purchase_order_item_id' => $item->id, 'quantity' => 6],
    ]);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($inventory->fresh()->quantity)->toBe(10)
        ->and(JournalEntry::query()->where('entry_type', 'goods_receipt')->count())->toBe(2)
        ->and($receipt2->items)->toHaveCount(1);
});

test('unbalanced journal draft is rejected and balanced entry posts', function (): void {
    $cash = Account::query()->where('code', '1000')->firstOrFail();
    $sales = Account::query()->where('code', '4000')->firstOrFail();
    $journals = app(JournalEntryService::class);

    expect(fn () => $journals->createDraft(
        'JE-BAD-1',
        'Unbalanced',
        now()->toDateString(),
        [
            ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0'],
            ['account_id' => $sales->id, 'debit' => '0', 'credit' => '50.00'],
        ],
    ))->toThrow(ValidationException::class);

    $entry = $journals->createDraft(
        'JE-OK-1',
        'Balanced',
        now()->toDateString(),
        [
            ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0'],
            ['account_id' => $sales->id, 'debit' => '0', 'credit' => '100.00'],
        ],
    );

    $posted = $journals->post($entry);

    expect($posted->status)->toBe(JournalEntryStatus::Posted)
        ->and($posted->posted_at)->not->toBeNull()
        ->and($posted->lines)->toHaveCount(2);
});
