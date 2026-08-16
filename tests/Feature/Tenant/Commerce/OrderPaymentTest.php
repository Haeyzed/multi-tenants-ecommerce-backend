<?php

declare(strict_types=1);

use App\Contracts\Payment\PaymentGateway;
use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentRefundResult;
use App\DTO\Payment\PaymentVerificationResult;
use App\Enums\Tenant\Accounting\JournalEntryStatus;
use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use App\Models\Tenant\Warehouse;
use App\Services\Payment\PaymentManager;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\OrderPaymentService;
use App\Services\Tenant\Commerce\OrderTransitionService;
use App\Services\Tenant\Inventory\InventoryService;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        '2026_08_15_060016_create_accounts_table.php',
        '2026_08_15_060017_create_journal_entries_table.php',
        '2026_08_15_060018_create_journal_entry_lines_table.php',
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
 * @return array{customer: Customer, address: CustomerAddress, product: Product, inventory: Inventory, order: Order}
 */
function paymentFixture(int $stock = 10): array
{
    Event::fake([OrderPaid::class, OrderCreated::class, OrderCancelled::class]);

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
    app(InventoryService::class)->adjust($inventory, $stock, InventoryMovementType::OpeningStock, 'Opening');

    app(CartService::class)->addItem($customer, $product->id, null, 2);
    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
    ]);

    return [
        'customer' => $customer,
        'address' => $address,
        'product' => $product,
        'inventory' => $inventory->fresh(),
        'order' => $order,
    ];
}

function mockPaymentGateway(
    bool $verifySuccess = true,
    string $amount = '200.00',
    string $currency = 'NGN',
): PaymentGateway {
    return new class($verifySuccess, $amount, $currency) implements PaymentGateway
    {
        public function __construct(
            private readonly bool $verifySuccess,
            private readonly string $amount,
            private readonly string $currency,
        ) {}

        public function name(): string
        {
            return 'paystack';
        }

        public function initializePayment(PaymentInitiationRequest $request): PaymentInitiationResult
        {
            return new PaymentInitiationResult(
                reference: $request->reference,
                authorizationUrl: 'https://paystack.test/authorize/'.$request->reference,
                accessCode: 'access_test',
                provider: 'paystack',
            );
        }

        public function verifyPayment(string $reference): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                successful: $this->verifySuccess,
                reference: $reference,
                providerTransactionId: 'txn_'.$reference,
                amount: $this->amount,
                currency: $this->currency,
                paidAt: now(),
            );
        }

        public function getPaymentStatus(string $reference): PaymentVerificationResult
        {
            return $this->verifyPayment($reference);
        }

        public function supportsCurrency(string $currency): bool
        {
            return true;
        }

        public function supportedCurrencies(): array
        {
            return ['NGN', 'USD'];
        }

        public function supportedMethods(): array
        {
            return ['card'];
        }

        public function refundPayment(string $providerTransactionId, ?string $amount = null): bool
        {
            return false;
        }

        public function refundPaymentDetailed(
            string $providerTransactionId,
            ?string $amount = null,
            ?string $currency = null,
        ): PaymentRefundResult {
            return new PaymentRefundResult(successful: false);
        }
    };
}

test('initialize creates pending order payment via gateway', function (): void {
    $fixture = paymentFixture();
    $gateway = mockPaymentGateway();

    $this->mock(PaymentManager::class, function ($mock) use ($gateway): void {
        $mock->shouldReceive('driver')->andReturn($gateway);
    });

    $result = app(OrderPaymentService::class)->initialize($fixture['order'], $fixture['customer']);

    expect($result['payment']->status)->toBe(OrderPaymentRecordStatus::Pending)
        ->and($result['payment']->reference)->toStartWith('ORDPAY-')
        ->and($result['initiation']->authorizationUrl)->toContain('https://paystack.test/')
        ->and($fixture['order']->fresh()->payment_status)->toBe(OrderPaymentStatus::Pending);
});

test('verify success commits stock posts journal and fires OrderPaid', function (): void {
    $fixture = paymentFixture();
    $gateway = mockPaymentGateway(verifySuccess: true);

    $this->mock(PaymentManager::class, function ($mock) use ($gateway): void {
        $mock->shouldReceive('driver')->andReturn($gateway);
    });

    $service = app(OrderPaymentService::class);
    $init = $service->initialize($fixture['order'], $fixture['customer']);
    $payment = $service->verify($init['payment']->reference);

    expect($payment->status)->toBe(OrderPaymentRecordStatus::Successful)
        ->and($payment->order->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($payment->order->status)->toBe(OrderStatus::Confirmed)
        ->and($fixture['inventory']->fresh()->quantity)->toBe(8)
        ->and($fixture['inventory']->fresh()->reserved_quantity)->toBe(0);

    $journals = JournalEntry::query()
        ->where('source_type', Order::class)
        ->where('source_id', $fixture['order']->id)
        ->where('entry_type', 'sale')
        ->get();

    expect($journals)->toHaveCount(1)
        ->and($journals->first()->status)->toBe(JournalEntryStatus::Posted);

    Event::assertDispatched(OrderPaid::class);
});

test('duplicate verify does not double post journal or restock', function (): void {
    $fixture = paymentFixture();
    $gateway = mockPaymentGateway(verifySuccess: true);

    $this->mock(PaymentManager::class, function ($mock) use ($gateway): void {
        $mock->shouldReceive('driver')->andReturn($gateway);
    });

    $service = app(OrderPaymentService::class);
    $init = $service->initialize($fixture['order'], $fixture['customer']);
    $service->verify($init['payment']->reference);
    $service->verify($init['payment']->reference);

    expect(OrderPayment::query()->where('reference', $init['payment']->reference)->count())->toBe(1)
        ->and(JournalEntry::query()->where('entry_type', 'sale')->where('source_id', $fixture['order']->id)->count())->toBe(1)
        ->and($fixture['inventory']->fresh()->quantity)->toBe(8);
});

test('verify rejects amount mismatch and does not mark paid', function (): void {
    $fixture = paymentFixture();
    $gateway = mockPaymentGateway(verifySuccess: true, amount: '1.00', currency: 'NGN');

    $this->mock(PaymentManager::class, function ($mock) use ($gateway): void {
        $mock->shouldReceive('driver')->andReturn($gateway);
    });

    $service = app(OrderPaymentService::class);
    $init = $service->initialize($fixture['order'], $fixture['customer']);

    expect(fn () => $service->verify($init['payment']->reference))
        ->toThrow(ValidationException::class);

    expect($init['payment']->fresh()->status)->toBe(OrderPaymentRecordStatus::Failed)
        ->and($fixture['order']->fresh()->payment_status)->not->toBe(OrderPaymentStatus::Paid)
        ->and(JournalEntry::query()->where('entry_type', 'sale')->count())->toBe(0)
        ->and($fixture['inventory']->fresh()->reserved_quantity)->toBe(2);
});

test('verifyForCustomer blocks other customers before side effects', function (): void {
    $fixture = paymentFixture();
    $other = Customer::factory()->create();
    $gateway = mockPaymentGateway(verifySuccess: true);

    $this->mock(PaymentManager::class, function ($mock) use ($gateway): void {
        $mock->shouldReceive('driver')->andReturn($gateway);
    });

    $service = app(OrderPaymentService::class);
    $init = $service->initialize($fixture['order'], $fixture['customer']);

    expect(fn () => $service->verifyForCustomer($init['payment']->reference, $other))
        ->toThrow(AccessDeniedHttpException::class);

    expect($init['payment']->fresh()->status)->toBe(OrderPaymentRecordStatus::Pending)
        ->and(JournalEntry::query()->where('entry_type', 'sale')->count())->toBe(0);
});

test('webhook without valid signature is rejected', function (): void {
    config(['payment.drivers.paystack.webhook_secret' => 'test_secret']);

    $fixture = paymentFixture();
    $gateway = mockPaymentGateway(verifySuccess: true);

    $this->mock(PaymentManager::class, function ($mock) use ($gateway): void {
        $mock->shouldReceive('driver')->andReturn($gateway);
    });

    $service = app(OrderPaymentService::class);
    $init = $service->initialize($fixture['order'], $fixture['customer']);

    $payload = [
        'event' => 'charge.success',
        'data' => ['reference' => $init['payment']->reference, 'id' => '999'],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    expect(fn () => $service->handleWebhook($payload, 'bad-signature', $raw))
        ->toThrow(AccessDeniedHttpException::class);
});

test('duplicate paystack webhook event ids are ignored after the first successful process', function (): void {
    config(['payment.drivers.paystack.webhook_secret' => 'test_secret']);

    $this->artisan('migrate', [
        '--path' => database_path('migrations/tenant/2026_08_16_071511_create_payment_webhook_events_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $fixture = paymentFixture();
    $gateway = mockPaymentGateway(verifySuccess: true);

    $this->mock(PaymentManager::class, function ($mock) use ($gateway): void {
        $mock->shouldReceive('driver')->andReturn($gateway);
    });

    $service = app(OrderPaymentService::class);
    $init = $service->initialize($fixture['order'], $fixture['customer']);

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'id' => 424242,
            'reference' => $init['payment']->reference,
        ],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha512', $raw, 'test_secret');

    $first = $service->handleWebhook($payload, $signature, $raw);
    $second = $service->handleWebhook($payload, $signature, $raw);

    expect($first['processed'])->toBeTrue()
        ->and($first['payment']->status)->toBe(OrderPaymentRecordStatus::Successful)
        ->and($second['processed'])->toBeFalse()
        ->and($second['duplicate'] ?? false)->toBeTrue()
        ->and(OrderPayment::query()->where('order_id', $fixture['order']->id)->count())->toBe(1)
        ->and(JournalEntry::query()->where('entry_type', 'sale')->count())->toBe(1);
});

test('paid order cannot be cancelled via transition', function (): void {
    $fixture = paymentFixture();
    $gateway = mockPaymentGateway(verifySuccess: true);

    $this->mock(PaymentManager::class, function ($mock) use ($gateway): void {
        $mock->shouldReceive('driver')->andReturn($gateway);
    });

    $service = app(OrderPaymentService::class);
    $init = $service->initialize($fixture['order'], $fixture['customer']);
    $service->verify($init['payment']->reference);

    expect(fn () => app(OrderTransitionService::class)
        ->transition($fixture['order']->fresh(), OrderStatus::Cancelled))
        ->toThrow(ValidationException::class);
});
