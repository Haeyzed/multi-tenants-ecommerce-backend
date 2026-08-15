<?php

declare(strict_types=1);

use App\Enums\Tenant\Accounting\AccountType;
use App\Enums\Tenant\Commerce\CartStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Procurement\SupplierStatus;
use App\Models\Tenant\Account;
use App\Models\Tenant\Cart;
use App\Models\Tenant\CommerceSetting;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\ShippingMethod;
use App\Models\Tenant\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

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
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_041028_create_customer_addresses_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060003_create_carts_table.php',
        '2026_08_15_060004_create_cart_items_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060006_create_order_items_table.php',
        '2026_08_15_060007_create_checkout_sessions_table.php',
        '2026_08_15_060008_create_order_payments_table.php',
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
});

test('commerce migrations create expected tables', function (): void {
    expect(Schema::hasTable('commerce_settings'))->toBeTrue()
        ->and(Schema::hasTable('carts'))->toBeTrue()
        ->and(Schema::hasTable('cart_items'))->toBeTrue()
        ->and(Schema::hasTable('checkout_sessions'))->toBeTrue()
        ->and(Schema::hasTable('shipping_methods'))->toBeTrue()
        ->and(Schema::hasTable('orders'))->toBeTrue()
        ->and(Schema::hasTable('order_items'))->toBeTrue()
        ->and(Schema::hasTable('order_payments'))->toBeTrue()
        ->and(Schema::hasTable('shipments'))->toBeTrue()
        ->and(Schema::hasTable('suppliers'))->toBeTrue()
        ->and(Schema::hasTable('supplier_contacts'))->toBeTrue()
        ->and(Schema::hasTable('purchase_orders'))->toBeTrue()
        ->and(Schema::hasTable('purchase_order_items'))->toBeTrue()
        ->and(Schema::hasTable('goods_receipts'))->toBeTrue()
        ->and(Schema::hasTable('goods_receipt_items'))->toBeTrue()
        ->and(Schema::hasTable('accounts'))->toBeTrue()
        ->and(Schema::hasTable('journal_entries'))->toBeTrue()
        ->and(Schema::hasTable('journal_entry_lines'))->toBeTrue();
});

test('commerce factories create records and customer relations work', function (): void {
    $customer = Customer::factory()->create();
    $cart = Cart::factory()->for($customer)->create();
    $order = Order::factory()->for($customer)->create();
    $supplier = Supplier::factory()->create();
    $shippingMethod = ShippingMethod::factory()->create();
    $account = Account::factory()->create(['type' => AccountType::Asset]);

    CommerceSetting::query()->create([
        'key' => 'tax_rate',
        'value' => '0.10',
    ]);

    expect($cart->status)->toBe(CartStatus::Active)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($supplier->status)->toBe(SupplierStatus::Active)
        ->and($shippingMethod->is_active)->toBeTrue()
        ->and($account->type)->toBe(AccountType::Asset)
        ->and($customer->carts)->toHaveCount(1)
        ->and($customer->orders)->toHaveCount(1)
        ->and(CommerceSetting::query()->where('key', 'tax_rate')->value('value'))->toBe('0.10');
});
