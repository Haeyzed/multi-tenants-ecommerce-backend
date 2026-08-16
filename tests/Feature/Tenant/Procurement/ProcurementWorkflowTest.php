<?php

declare(strict_types=1);

use App\Enums\Tenant\Procurement\PurchaseOrderStatus;
use App\Http\Resources\Tenant\Procurement\PurchaseOrderResource;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Procurement\GoodsReceiptService;
use App\Services\Tenant\Procurement\PurchaseOrderService;
use App\Services\Tenant\Procurement\SupplierService;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);
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

/**
 * @return array{0: PurchaseOrder, 1: PurchaseOrderService, 2: GoodsReceiptService}
 */
function procurementWorkflowSetup(int $quantity = 10): array
{
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $product = Product::factory()->active()->create();
    $supplier = app(SupplierService::class)->store([
        'name' => 'Workflow Supplies',
        'code' => 'WFS-'.bin2hex(random_bytes(2)),
    ]);

    $poService = app(PurchaseOrderService::class);
    $po = $poService->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'currency' => 'NGN',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => '25.00',
            ],
        ],
    ]);

    return [$po, $poService, app(GoodsReceiptService::class)];
}

test('over-receive is rejected', function (): void {
    [$po, $poService, $receiptService] = procurementWorkflowSetup(5);
    $po = $poService->approve($po);
    $po = $poService->markOrdered($po);
    $item = $po->items->first();

    expect(fn () => $receiptService->receive($po, [
        ['purchase_order_item_id' => $item->id, 'quantity' => 6],
    ]))->toThrow(ValidationException::class);
});

test('receive on draft is rejected', function (): void {
    [$po, , $receiptService] = procurementWorkflowSetup();
    $item = $po->items->first();

    expect(fn () => $receiptService->receive($po, [
        ['purchase_order_item_id' => $item->id, 'quantity' => 1],
    ]))->toThrow(ValidationException::class);
});

test('receive on approved is rejected until marked ordered', function (): void {
    [$po, $poService, $receiptService] = procurementWorkflowSetup();
    $po = $poService->approve($po);
    $item = $po->items->first();

    expect(fn () => $receiptService->receive($po, [
        ['purchase_order_item_id' => $item->id, 'quantity' => 1],
    ]))->toThrow(ValidationException::class);
});

test('cancel draft succeeds', function (): void {
    [$po, $poService] = procurementWorkflowSetup();

    $cancelled = $poService->cancel($po);

    expect($cancelled->status)->toBe(PurchaseOrderStatus::Cancelled);
});

test('cancel after partial receive is rejected', function (): void {
    [$po, $poService, $receiptService] = procurementWorkflowSetup();
    $po = $poService->approve($po);
    $po = $poService->markOrdered($po);
    $item = $po->items->first();

    $receiptService->receive($po, [
        ['purchase_order_item_id' => $item->id, 'quantity' => 3],
    ]);

    expect(fn () => $poService->cancel($po->fresh()))->toThrow(ValidationException::class);
});

test('close partially received succeeds', function (): void {
    [$po, $poService, $receiptService] = procurementWorkflowSetup();
    $po = $poService->approve($po);
    $po = $poService->markOrdered($po);
    $item = $po->items->first();

    $receiptService->receive($po, [
        ['purchase_order_item_id' => $item->id, 'quantity' => 4],
    ]);

    $closed = $poService->close($po->fresh());

    expect($closed->status)->toBe(PurchaseOrderStatus::Closed);
});

test('purchase order show includes goods receipts after receive', function (): void {
    [$po, $poService, $receiptService] = procurementWorkflowSetup();
    $po = $poService->approve($po);
    $po = $poService->markOrdered($po);
    $item = $po->items->first();

    $receiptService->receive($po, [
        ['purchase_order_item_id' => $item->id, 'quantity' => 2],
    ]);

    $shown = $poService->show($po->fresh());
    $payload = (new PurchaseOrderResource($shown))->toArray(Request::create('/'));

    expect($shown->goodsReceipts)->toHaveCount(1)
        ->and($payload)->toHaveKey('goods_receipts')
        ->and($payload['goods_receipts'])->not->toBeEmpty();
});

test('supplier destroy is blocked when open purchase order exists', function (): void {
    [$po] = procurementWorkflowSetup();
    $supplier = $po->supplier;
    $supplierService = app(SupplierService::class);

    expect(fn () => $supplierService->destroy($supplier))->toThrow(ValidationException::class);

    app(PurchaseOrderService::class)->cancel($po);

    $supplierService->destroy($supplier->fresh());

    expect($supplier->fresh()->trashed())->toBeTrue();
});
