<?php

declare(strict_types=1);

use App\Contracts\Payment\PaymentGateway;
use App\DTO\Payment\PaymentInitiationRequest;
use App\DTO\Payment\PaymentInitiationResult;
use App\DTO\Payment\PaymentRefundResult;
use App\DTO\Payment\PaymentVerificationResult;
use App\Enums\Tenant\Marketplace\SellerCommissionStatus;
use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Enums\Tenant\Marketplace\SellerPayoutStatus;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\Product;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerCommission;
use App\Models\Tenant\SellerOrder;
use App\Models\Tenant\SellerPayout;
use App\Models\Tenant\Warehouse;
use App\Services\Payment\PaymentManager;
use App\Services\Tenant\Commerce\CartService;
use App\Services\Tenant\Commerce\CheckoutService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Commerce\OrderPaymentService;
use App\Services\Tenant\Marketplace\SellerOfferService;
use App\Services\Tenant\Marketplace\SellerOrderTransitionService;
use App\Services\Tenant\Marketplace\SellerPayoutService;
use Database\Seeders\Tenant\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

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
        '2026_08_16_160758_make_sellers_authenticatable_table.php',
        '2026_08_15_070003_create_seller_offers_table.php',
        '2026_08_15_070004_add_seller_offer_to_cart_and_order_items.php',
        '2026_08_15_070005_create_seller_orders_tables.php',
        '2026_08_15_070006_create_seller_commissions_table.php',
        '2026_08_15_070007_create_seller_payouts_table.php',
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

    app(CommerceSettingService::class)->setMarketplaceEnabled(true);
    app(CommerceSettingService::class)->set('marketplace.commission_rate', '10');
    app(CommerceSettingService::class)->set('marketplace.refund_window_days', '0');

    $this->seed(ChartOfAccountsSeeder::class);
});

function mockMarketplacePaymentGateway(string $amount = '200.00'): PaymentGateway
{
    return new class($amount) implements PaymentGateway
    {
        public function __construct(private readonly string $amount) {}

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
                successful: true,
                reference: $reference,
                providerTransactionId: 'txn_'.$reference,
                amount: $this->amount,
                currency: 'NGN',
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
            return ['NGN'];
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

/**
 * @return array{customer: Customer, seller: Seller, sellerOrder: SellerOrder, commission: SellerCommission}
 */
function paidMarketplaceOrderFixture(): array
{
    Event::fake([OrderPaid::class, OrderCreated::class]);

    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->default()->create();
    $seller = Seller::factory()->sellable()->create();
    $product = Product::factory()->active()->create();
    Warehouse::factory()->create(['is_default' => true]);

    $offer = app(SellerOfferService::class)->store([
        'seller_id' => $seller->id,
        'product_id' => $product->id,
        'price' => '100.00',
        'stock' => 10,
    ]);

    app(CartService::class)->addItem($customer, $product->id, null, 2, $offer->id);
    $order = app(CheckoutService::class)->checkout($customer, [
        'shipping_address_id' => $address->id,
    ]);

    $gateway = mockMarketplacePaymentGateway((string) $order->grand_total);

    $manager = Mockery::mock(PaymentManager::class);
    $manager->shouldReceive('driver')->andReturn($gateway);
    app()->instance(PaymentManager::class, $manager);
    app()->forgetInstance(OrderPaymentService::class);

    $init = app(OrderPaymentService::class)->initialize($order, $customer);
    app(OrderPaymentService::class)->markSuccessful($init['payment']);

    $sellerOrder = SellerOrder::query()->where('order_id', $order->id)->firstOrFail();
    $commission = SellerCommission::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail();

    return compact('customer', 'seller', 'sellerOrder', 'commission');
}

test('payment success creates commission and marketplace sale journal', function (): void {
    $fixture = paidMarketplaceOrderFixture();

    expect($fixture['commission']->status)->toBe(SellerCommissionStatus::Earned)
        ->and($fixture['commission']->commission_amount)->toBe('20.00')
        ->and($fixture['commission']->seller_amount)->toBe('180.00')
        ->and($fixture['sellerOrder']->fresh()->commission_total)->toBe('20.00')
        ->and($fixture['sellerOrder']->fresh()->seller_total)->toBe('180.00');

    $saleJournal = JournalEntry::query()
        ->where('entry_type', 'sale')
        ->where('source_id', $fixture['sellerOrder']->order_id)
        ->first();

    expect($saleJournal)->not->toBeNull();
});

test('payout requires delivered seller order and posts payout journal', function (): void {
    $fixture = paidMarketplaceOrderFixture();
    $transitions = app(SellerOrderTransitionService::class);

    $sellerOrder = $fixture['sellerOrder']->fresh();
    $sellerOrder = $transitions->transition($sellerOrder, SellerOrderStatus::Processing);
    $sellerOrder = $transitions->transition($sellerOrder, SellerOrderStatus::ReadyToShip);
    $sellerOrder = $transitions->transition($sellerOrder, SellerOrderStatus::Shipped);
    $sellerOrder = $transitions->transition($sellerOrder, SellerOrderStatus::Delivered);

    $payout = app(SellerPayoutService::class)->create([
        'seller_id' => $fixture['seller']->id,
        'commission_ids' => [$fixture['commission']->id],
        'idempotency_key' => 'payout-key-1',
    ]);

    expect($payout->status)->toBe(SellerPayoutStatus::Paid)
        ->and($payout->amount)->toBe('180.00')
        ->and($fixture['commission']->fresh()->status)->toBe(SellerCommissionStatus::Paid);

    $payoutJournal = JournalEntry::query()
        ->where('entry_type', 'payout')
        ->where('source_id', $payout->id)
        ->first();

    expect($payoutJournal)->not->toBeNull();
});

test('payout idempotency returns same payout', function (): void {
    $fixture = paidMarketplaceOrderFixture();
    $transitions = app(SellerOrderTransitionService::class);

    $sellerOrder = $fixture['sellerOrder']->fresh();
    foreach ([SellerOrderStatus::Processing, SellerOrderStatus::ReadyToShip, SellerOrderStatus::Shipped, SellerOrderStatus::Delivered] as $status) {
        $sellerOrder = $transitions->transition($sellerOrder, $status);
    }

    $service = app(SellerPayoutService::class);
    $first = $service->create([
        'seller_id' => $fixture['seller']->id,
        'commission_ids' => [$fixture['commission']->id],
        'idempotency_key' => 'payout-key-dup',
    ]);

    $second = $service->create([
        'seller_id' => $fixture['seller']->id,
        'commission_ids' => [$fixture['commission']->id],
        'idempotency_key' => 'payout-key-dup',
    ]);

    expect($second->id)->toBe($first->id)
        ->and(SellerPayout::query()->count())->toBe(1);
});
