<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\InvoiceStatus;
use App\Events\OrderCreated;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\InvoiceService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (invoiceMigrationFiles() as $file) {
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
function invoiceMigrationFiles(): array
{
    return [
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
        '2026_08_15_070001_create_sellers_table.php',
        '2026_08_15_070003_create_seller_offers_table.php',
        '2026_08_15_070004_add_seller_offer_to_cart_and_order_items.php',
        '2026_08_15_080001_create_taxes_table.php',
        '2026_08_15_080002_create_tax_rates_table.php',
        '2026_08_15_080003_create_tax_zones_table.php',
        '2026_08_15_080004_create_tax_zone_locations_table.php',
        '2026_08_15_080005_create_tax_rules_table.php',
        '2026_08_15_080006_add_tax_snapshot_to_orders_table.php',
        '2026_08_15_080007_create_invoices_table.php',
        '2026_08_15_080008_create_invoice_items_table.php',
    ];
}

/**
 * @return array{customer: Customer, order: Order}
 */
function invoiceFixture(): array
{
    Event::fake([OrderCreated::class]);
    Queue::fake([GenerateInvoicePdfJob::class]);

    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->default()->create();
    $product = Product::factory()->active()->create(['allow_backorder' => false]);
    ProductPrice::query()->create([
        'priceable_type' => Product::class,
        'priceable_id' => $product->id,
        'currency' => 'NGN',
        'amount' => '150.00',
        'is_active' => true,
    ]);
    $warehouse = Warehouse::factory()->create(['is_default' => true]);
    $inventory = app(InventoryService::class)->getOrCreate($warehouse, $product);
    app(InventoryService::class)->adjust($inventory, 5, InventoryMovementType::OpeningStock, 'Opening');

    app(CartService::class)->addItem($customer, $product->id, null, 2);
    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
    ]);

    return compact('customer', 'order');
}

test('invoice service generates unique invoice numbers and items', function (): void {
    $fixture = invoiceFixture();
    $service = app(InvoiceService::class);

    $invoice = $service->generateForOrder($fixture['order'], queuePdf: false);

    expect($invoice->invoice_number)->toStartWith('INV-'.now()->format('Y').'-')
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->grand_total)->toBe($fixture['order']->grand_total);

    $again = $service->generateForOrder($fixture['order'], queuePdf: false);
    expect($again->id)->toBe($invoice->id)
        ->and(Invoice::query()->count())->toBe(1);
});

test('customer can only access own invoice', function (): void {
    $fixture = invoiceFixture();
    $service = app(InvoiceService::class);
    $invoice = $service->generateForOrder($fixture['order'], queuePdf: false);
    $other = Customer::factory()->create();

    expect(fn () => $service->customerShow($other, $invoice))
        ->toThrow(AccessDeniedHttpException::class);
});

test('generate invoice pdf job attaches document media', function (): void {
    $fixture = invoiceFixture();
    $invoice = app(InvoiceService::class)->generateForOrder($fixture['order'], queuePdf: false);

    (new GenerateInvoicePdfJob($invoice->id))->handle();

    expect($invoice->fresh()->getFirstMedia('documents'))->not->toBeNull();
});
