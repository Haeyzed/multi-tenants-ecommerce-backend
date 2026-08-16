<?php

declare(strict_types=1);

use App\Enums\Tenant\Commerce\FulfillmentStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\MarkAbandonedCartsJob;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Services\Notification\NotificationService;
use App\Services\Payment\Gateways\PaystackGateway;
use App\Services\Tenant\Commerce\CommerceAnalyticsService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Services\Tenant\Commerce\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
        'payment.drivers.paystack.secret_key' => 'sk_test_dummy',
        'payment.drivers.paystack.public_key' => 'pk_test_dummy',
        'payment.drivers.paystack.webhook_secret' => 'whsec_test',
        'payment.drivers.paystack.currencies' => ['NGN', 'GHS', 'ZAR', 'USD'],
    ]);

    foreach ([
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_060008_create_order_payments_table.php',
    ] as $file) {
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
});

test('abandoned cart job exits safely when tenant id is missing from the central database', function (): void {
    expect(fn () => (new MarkAbandonedCartsJob('missing-tenant-id'))->handle(
        app(CommerceSettingService::class),
        app(NotificationService::class),
        app(CommerceAnalyticsService::class),
    ))->not->toThrow(Throwable::class);
});

test('invoice pdf job exits safely when tenant id is missing from the central database', function (): void {
    expect(fn () => (new GenerateInvoicePdfJob(1, 'missing-tenant-id'))->handle())
        ->not->toThrow(Throwable::class);
});

test('payment initialization rejects orders with zero remaining balance', function (): void {
    $customer = Customer::factory()->create();
    $order = Order::query()->create([
        'order_number' => 'ORD-ZERO-1',
        'customer_id' => $customer->id,
        'currency' => 'NGN',
        'status' => OrderStatus::Pending,
        'payment_status' => OrderPaymentStatus::Unpaid,
        'fulfillment_status' => FulfillmentStatus::Unfulfilled,
        'subtotal' => '0.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'shipping_total' => '0.00',
        'grand_total' => '0.00',
        'placed_at' => now(),
    ]);

    expect(fn () => app(OrderPaymentService::class)->initialize($order, $customer))
        ->toThrow(ValidationException::class);
});

test('paystack refund converts amounts using the payment currency not a hard-coded NGN', function (): void {
    Http::fake([
        'https://api.paystack.co/refund' => Http::response([
            'status' => true,
            'message' => 'Refund initiated',
            'data' => [
                'id' => 99,
                'amount' => 1000,
                'currency' => 'USD',
            ],
        ], 200),
    ]);

    $result = app(PaystackGateway::class)->refundPaymentDetailed('txn_123', '10.00', 'USD');

    expect($result->successful)->toBeTrue();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.paystack.co/refund'
            && (int) $request['amount'] === 1000
            && $request['transaction'] === 'txn_123';
    });
});
