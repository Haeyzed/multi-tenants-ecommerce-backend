<?php

declare(strict_types=1);

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Exceptions\Shipping\UnsupportedShippingCarrierException;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShippingMethod;
use App\Models\Tenant\Warehouse;
use App\Services\Shipping\Carriers\FakeCarrier;
use App\Services\Shipping\ShippingCarrierManager;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Shipping\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);

    foreach (shippingCarrierMigrationFiles() as $file) {
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
function shippingCarrierMigrationFiles(): array
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
        '2026_08_15_060009_create_shipments_table.php',
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
    ];
}

test('fake carrier returns rates and tracking', function (): void {
    $carrier = app(FakeCarrier::class);

    $rates = $carrier->getRates(['currency' => 'NGN']);
    expect($rates)->toHaveCount(2)
        ->and($rates[0]->amount)->toBe('500.00');

    $created = $carrier->createShipment(['order_id' => 1]);
    expect($created->successful)->toBeTrue()
        ->and($created->trackingNumber)->toStartWith('FAKE-');

    $tracking = $carrier->trackShipment((string) $created->trackingNumber);
    expect($tracking->found)->toBeTrue()
        ->and($tracking->status)->toBe('in_transit');
});

test('fake carrier cancels shipment and returns label', function (): void {
    $carrier = app(FakeCarrier::class);
    $tracking = 'FAKE-LABELTEST01';

    $cancelled = $carrier->cancelShipment($tracking);
    expect($cancelled->successful)->toBeTrue()
        ->and($cancelled->message)->toBe('Shipment cancelled.')
        ->and($cancelled->raw['tracking_number'])->toBe($tracking);

    $label = $carrier->getLabel($tracking);
    expect($label->successful)->toBeTrue()
        ->and($label->contentType)->toBe('application/pdf')
        ->and($label->contentBase64)->not->toBeEmpty()
        ->and(base64_decode((string) $label->contentBase64, true))->toContain('%PDF-1.4')
        ->and($label->url)->toBe('https://example.test/labels/'.$tracking.'.pdf');
});

test('shipping carrier manager resolves fake driver', function (): void {
    $manager = app(ShippingCarrierManager::class);
    expect($manager->driver('fake'))->toBeInstanceOf(FakeCarrier::class)
        ->and($manager->drivers())->toContain('fake')
        ->and($manager->isScaffoldAlias('dhl'))->toBeTrue()
        ->and($manager->isScaffoldAlias('fake'))->toBeFalse();
});

test('shipping carrier manager throws for unsupported driver', function (): void {
    $manager = app(ShippingCarrierManager::class);

    expect(fn () => $manager->driver('not-a-carrier'))
        ->toThrow(UnsupportedShippingCarrierException::class);
});

test('shipment service optionally uses carrier when enabled', function (): void {
    Event::fake([OrderCreated::class, OrderPaid::class]);

    config(['shipping.use_carriers' => true, 'shipping.method_carriers' => ['standard' => 'fake']]);

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

    $method = ShippingMethod::factory()->create(['code' => 'standard', 'amount' => '500.00']);

    app(CartService::class)->addItem($customer, $product->id, null, 1);
    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
        'shipping_method_id' => $method->id,
    ]);

    $shipment = app(ShipmentService::class)->create($order);

    expect($shipment->tracking_number)->toStartWith('FAKE-')
        ->and($shipment->carrier)->toBe('fake')
        ->and($shipment->status)->toBe(ShipmentStatus::Pending);
});

test('shipment service tracks cancels and labels via carrier', function (): void {
    $order = Order::factory()->create();
    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'tracking_number' => 'FAKE-VIA-CARRIER',
        'carrier' => 'fake',
        'status' => ShipmentStatus::Pending,
    ]);

    $service = app(ShipmentService::class);

    $tracking = $service->trackViaCarrier($shipment);
    expect($tracking->found)->toBeTrue()
        ->and($tracking->status)->toBe('in_transit');

    $label = $service->labelViaCarrier($shipment);
    expect($label->successful)->toBeTrue()
        ->and($label->contentType)->toBe('application/pdf');

    $cancelled = $service->cancelViaCarrier($shipment);
    expect($cancelled->successful)->toBeTrue()
        ->and($shipment->fresh()->status)->toBe(ShipmentStatus::Cancelled);
});

test('shipment service applyCarrierStatus maps delivered', function (): void {
    $order = Order::factory()->create();
    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'tracking_number' => 'FAKE-APPLY-STATUS',
        'carrier' => 'fake',
        'status' => ShipmentStatus::Shipped,
    ]);

    $updated = app(ShipmentService::class)->applyCarrierStatus('FAKE-APPLY-STATUS', 'Delivered');

    expect($updated)->not->toBeNull()
        ->and($updated->status)->toBe(ShipmentStatus::Delivered)
        ->and($updated->delivered_at)->not->toBeNull();
});
