<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Pos\PosCashMovementType;
use App\Enums\Tenant\Pos\PosSessionStatus;
use App\Enums\Tenant\Pos\PosTerminalStatus;
use App\Enums\Tenant\Pos\SalesChannel;
use App\Events\POSSaleCompleted;
use App\Events\POSSessionClosed;
use App\Events\POSSessionOpened;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\PosCashMovement;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Pos\PosCatalogService;
use App\Services\Tenant\Pos\PosSaleService;
use App\Services\Tenant\Pos\PosSessionService;
use App\Services\Tenant\Pos\PosTerminalService;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
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
        '2026_08_15_050001_add_catalogue_control_fields_to_products_and_variants_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_060008_create_order_payments_table.php',
        '2026_08_15_060016_create_accounts_table.php',
        '2026_08_15_060017_create_journal_entries_table.php',
        '2026_08_15_060018_create_journal_entry_lines_table.php',
        '2026_08_15_080001_create_taxes_table.php',
        '2026_08_15_080002_create_tax_rates_table.php',
        '2026_08_15_080003_create_tax_zones_table.php',
        '2026_08_15_080004_create_tax_zone_locations_table.php',
        '2026_08_15_080005_create_tax_rules_table.php',
        '2026_08_15_080006_add_tax_snapshot_to_orders_table.php',
        '2026_08_15_080009_create_refunds_table.php',
        '2026_08_16_073620_add_nullable_order_payment_id_to_refunds_table.php',
        '2026_08_16_124748_create_pos_terminals_table.php',
        '2026_08_16_124802_create_pos_sessions_table.php',
        '2026_08_16_124816_create_pos_cash_movements_table.php',
        '2026_08_16_124831_add_pos_fields_to_orders_table.php',
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
 * @return array{
 *     user: User,
 *     warehouse: Warehouse,
 *     terminal: PosTerminal,
 *     product: Product,
 *     variant: ProductVariant,
 *     inventory: Inventory
 * }
 */
function posFixture(int $stock = 10): array
{
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $terminal = PosTerminal::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => PosTerminalStatus::Active,
    ]);

    $product = Product::factory()->active()->create(['allow_backorder' => false, 'has_variants' => true]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'barcode' => 'BARCODE-POS-001',
        'sku' => 'SKU-POS-001',
        'is_active' => true,
        'allow_backorder' => false,
    ]);

    ProductPrice::query()->create([
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
        'currency' => 'NGN',
        'amount' => '50.00',
        'is_active' => true,
    ]);

    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $variant);
    app(InventoryService::class)->adjust($inventory, $stock, InventoryMovementType::OpeningStock, 'Opening');

    return [
        'user' => $user,
        'warehouse' => $warehouse,
        'terminal' => $terminal,
        'product' => $product,
        'variant' => $variant,
        'inventory' => $inventory->fresh(),
    ];
}

test('pos terminal crud and code isolation', function (): void {
    $warehouse = Warehouse::factory()->create();
    $service = app(PosTerminalService::class);

    $terminal = $service->store([
        'name' => 'Front Counter',
        'code' => 'FRONT-1',
        'warehouse_id' => $warehouse->id,
        'location_label' => 'Lobby',
    ]);

    expect($terminal->code)->toBe('FRONT-1')
        ->and($terminal->status)->toBe(PosTerminalStatus::Active);

    $updated = $service->update($terminal, ['name' => 'Front Counter A']);
    expect($updated->name)->toBe('Front Counter A');

    expect($service->list(['search' => 'Front'])->total())->toBe(1);
    expect($service->options())->not->toBeEmpty();

    $service->destroy($updated);
    expect(PosTerminal::query()->whereKey($terminal->id)->exists())->toBeFalse();
});

test('cannot open second session on same terminal', function (): void {
    Event::fake([POSSessionOpened::class]);

    $fixture = posFixture();
    $sessions = app(PosSessionService::class);

    $first = $sessions->open($fixture['terminal'], $fixture['user'], '100.00');
    expect($first->status)->toBe(PosSessionStatus::Open);
    Event::assertDispatched(POSSessionOpened::class);

    expect(fn () => $sessions->open($fixture['terminal'], $fixture['user'], '50.00'))
        ->toThrow(ValidationException::class);
});

test('session close computes cash variance', function (): void {
    Event::fake([POSSessionOpened::class, POSSessionClosed::class]);

    $fixture = posFixture();
    $sessions = app(PosSessionService::class);

    $session = $sessions->open($fixture['terminal'], $fixture['user'], '100.00');
    $sessions->cashIn($session, $fixture['user'], '20.00', 'Float top-up');
    $sessions->cashOut($session, $fixture['user'], '5.00', 'Petty cash');

    PosCashMovement::query()->create([
        'pos_session_id' => $session->id,
        'type' => PosCashMovementType::SaleCash,
        'amount' => '40.00',
        'reason' => 'Test sale cash',
        'user_id' => $fixture['user']->id,
    ]);

    // expected = 100 + 20 + 40 - 5 = 155
    expect($sessions->expectedCash($session))->toBe('155.00');

    $closed = $sessions->close($session, '150.00');
    expect($closed->status)->toBe(PosSessionStatus::Closed)
        ->and($closed->expected_cash)->toBe('155.00')
        ->and($closed->actual_cash)->toBe('150.00')
        ->and($closed->cash_difference)->toBe('-5.00');

    Event::assertDispatched(POSSessionClosed::class);
});

test('barcode lookup finds active variant', function (): void {
    $fixture = posFixture();
    $found = app(PosCatalogService::class)->findByBarcode('BARCODE-POS-001');

    expect($found)->toBeInstanceOf(ProductVariant::class)
        ->and($found->id)->toBe($fixture['variant']->id);
});

test('pos sale deducts inventory and records cash movement', function (): void {
    Event::fake([POSSaleCompleted::class, POSSessionOpened::class]);

    $fixture = posFixture(10);
    $sessions = app(PosSessionService::class);
    $sales = app(PosSaleService::class);

    $session = $sessions->open($fixture['terminal'], $fixture['user'], '100.00');

    $order = $sales->createSale($session, [
        'items' => [
            [
                'product_id' => $fixture['product']->id,
                'product_variant_id' => $fixture['variant']->id,
                'quantity' => 2,
            ],
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => '100.00'],
        ],
        'currency' => 'NGN',
    ]);

    expect($order->sales_channel)->toBe(SalesChannel::Pos)
        ->and($order->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->pos_terminal_id)->toBe($fixture['terminal']->id)
        ->and($order->pos_session_id)->toBe($session->id)
        ->and((float) $order->grand_total)->toBe(100.0);

    expect($fixture['inventory']->fresh()->quantity)->toBe(8);

    expect(PosCashMovement::query()
        ->where('pos_session_id', $session->id)
        ->where('type', PosCashMovementType::SaleCash)
        ->exists())->toBeTrue();

    Event::assertDispatched(POSSaleCompleted::class);
});

test('pos sale supports split cash and card payments', function (): void {
    Event::fake([POSSaleCompleted::class, POSSessionOpened::class]);

    $fixture = posFixture(5);
    $sessions = app(PosSessionService::class);
    $sales = app(PosSaleService::class);
    $session = $sessions->open($fixture['terminal'], $fixture['user'], '50.00');

    $order = $sales->createSale($session, [
        'items' => [
            [
                'product_id' => $fixture['product']->id,
                'product_variant_id' => $fixture['variant']->id,
                'quantity' => 2,
            ],
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => '40.00'],
            ['method' => 'card', 'amount' => '60.00'],
        ],
        'currency' => 'NGN',
    ]);

    expect($order->payments)->toHaveCount(2)
        ->and($order->payment_status)->toBe(OrderPaymentStatus::Paid);

    $gateways = $order->payments->pluck('gateway')->sort()->values()->all();
    expect($gateways)->toBe(['cash', 'offline_card']);
});

test('pos routes are registered behind feature middleware', function (): void {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->getName() === 'tenant.pos.terminals.index');

    expect($route)->not->toBeNull()
        ->and(implode(',', $route->gatherMiddleware()))->toContain('feature:pos');
});
