<?php

declare(strict_types=1);

use App\Enums\Tenant\Commerce\GiftCardStatus;
use App\Enums\Tenant\Commerce\GiftCardTransactionType;
use App\Models\Tenant\Customer;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\GiftCardTransaction;
use App\Models\Tenant\Order;
use App\Services\Tenant\Commerce\GiftCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);

    foreach (giftCardMigrationFiles() as $file) {
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
function giftCardMigrationFiles(): array
{
    return [
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_060002_create_shipping_methods_table.php',
        '2026_08_15_060005_create_orders_table.php',
        '2026_08_15_100201_create_gift_cards_table.php',
        '2026_08_15_100202_create_gift_card_transactions_table.php',
        '2026_08_15_100203_add_gift_card_fields_to_orders_table.php',
    ];
}

function giftCardOrder(string $currency = 'NGN'): Order
{
    return Order::factory()->create([
        'customer_id' => Customer::factory(),
        'currency' => $currency,
    ]);
}

test('creating a gift card returns the plain code once and stores only its hash', function (): void {
    [$giftCard, $plainCode] = app(GiftCardService::class)->create([
        'amount' => '100.00',
        'currency' => 'NGN',
        'activate' => true,
    ]);

    expect($plainCode)->toStartWith('GC-')
        ->and($giftCard->code_hash)->toBe(hash('sha256', strtoupper($plainCode)))
        ->and($giftCard->last_four)->toBe(substr($plainCode, -4))
        ->and($giftCard->initial_amount)->toBe('100.00')
        ->and($giftCard->balance)->toBe('100.00')
        ->and($giftCard->status)->toBe(GiftCardStatus::Active)
        ->and($giftCard->activated_at)->not->toBeNull();

    expect(GiftCard::query()->where('code_hash', $plainCode)->exists())->toBeFalse()
        ->and(app(GiftCardService::class)->findByCode(strtolower($plainCode))?->id)->toBe($giftCard->id);

    $ledger = GiftCardTransaction::query()->where('gift_card_id', $giftCard->id)->sole();

    expect($ledger->type)->toBe(GiftCardTransactionType::PurchaseActivate)
        ->and($ledger->amount)->toBe('100.00')
        ->and($ledger->balance_after)->toBe('100.00');
});

test('a card created without activation cannot be redeemed until activated', function (): void {
    $service = app(GiftCardService::class);
    [$giftCard, $plainCode] = $service->create(['amount' => '50.00', 'currency' => 'NGN']);

    expect($giftCard->status)->toBe(GiftCardStatus::Inactive);

    expect(fn () => $service->redeem($plainCode, '10.00', giftCardOrder()))
        ->toThrow(ValidationException::class);

    $service->activate($giftCard);

    $transaction = $service->redeem($plainCode, '10.00', giftCardOrder());

    expect($transaction->amount)->toBe('-10.00');
});

test('a partial redemption debits the balance and records a signed ledger entry', function (): void {
    $service = app(GiftCardService::class);
    $giftCard = GiftCard::factory()->withCode('GC-TEST-PART-0001')->balance('100.00')->create([
        'currency' => 'NGN',
    ]);
    $order = giftCardOrder();

    $transaction = $service->redeem('gc-test-part-0001', '30.00', $order);

    expect($transaction->type)->toBe(GiftCardTransactionType::Redeem)
        ->and($transaction->amount)->toBe('-30.00')
        ->and($transaction->balance_after)->toBe('70.00')
        ->and($transaction->order_id)->toBe($order->id);

    $giftCard->refresh();

    expect($giftCard->balance)->toBe('70.00')
        ->and($giftCard->status)->toBe(GiftCardStatus::Active);
});

test('redeeming the full balance marks the card depleted', function (): void {
    $service = app(GiftCardService::class);
    $giftCard = GiftCard::factory()->withCode('GC-TEST-FULL-0002')->balance('40.00')->create([
        'currency' => 'NGN',
    ]);

    $service->redeem($giftCard, '25.00', giftCardOrder());
    $service->redeem($giftCard->fresh(), '15.00', giftCardOrder());

    $giftCard->refresh();

    expect($giftCard->balance)->toBe('0.00')
        ->and($giftCard->status)->toBe(GiftCardStatus::Depleted)
        ->and(GiftCardTransaction::query()->where('gift_card_id', $giftCard->id)->where('type', GiftCardTransactionType::Redeem)->count())->toBe(2);

    expect(fn () => $service->redeem($giftCard->fresh(), '1.00', giftCardOrder()))
        ->toThrow(ValidationException::class);
});

test('redeeming more than the remaining balance is rejected and leaves the balance untouched', function (): void {
    $service = app(GiftCardService::class);
    $giftCard = GiftCard::factory()->balance('20.00')->create(['currency' => 'NGN']);

    expect(fn () => $service->redeem($giftCard, '20.01', giftCardOrder()))
        ->toThrow(ValidationException::class);

    expect($giftCard->fresh()->balance)->toBe('20.00')
        ->and(GiftCardTransaction::query()->where('gift_card_id', $giftCard->id)->count())->toBe(0);
});

test('an expired gift card is rejected and flipped to the expired status', function (): void {
    $service = app(GiftCardService::class);
    $giftCard = GiftCard::factory()->withCode('GC-TEST-EXPD-0003')->expired()->balance('75.00')->create([
        'currency' => 'NGN',
    ]);

    expect(fn () => $service->redeem($giftCard, '10.00', giftCardOrder()))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->resolveRedeemable('GC-TEST-EXPD-0003', 'NGN'))
        ->toThrow(ValidationException::class);

    expect($giftCard->fresh()->status)->toBe(GiftCardStatus::Expired)
        ->and($giftCard->fresh()->balance)->toBe('75.00');
});

test('a refund restores balance to the originating card and reactivates it', function (): void {
    $service = app(GiftCardService::class);
    $giftCard = GiftCard::factory()->balance('60.00')->create(['currency' => 'NGN']);
    $order = giftCardOrder();

    $service->redeem($giftCard, '60.00', $order);

    expect($giftCard->fresh()->status)->toBe(GiftCardStatus::Depleted);

    $restore = $service->restoreFromRefund($giftCard->fresh(), '60.00', $order);

    expect($restore->type)->toBe(GiftCardTransactionType::RefundRestore)
        ->and($restore->amount)->toBe('60.00')
        ->and($restore->balance_after)->toBe('60.00');

    expect($giftCard->fresh()->balance)->toBe('60.00')
        ->and($giftCard->fresh()->status)->toBe(GiftCardStatus::Active);
});

test('cancelling a card zeroes the balance and blocks redemption', function (): void {
    $service = app(GiftCardService::class);
    $giftCard = GiftCard::factory()->balance('35.00')->create(['currency' => 'NGN']);

    $cancelled = $service->cancel($giftCard, 'Issued in error.');

    expect($cancelled->status)->toBe(GiftCardStatus::Cancelled)
        ->and($cancelled->balance)->toBe('0.00')
        ->and(GiftCardTransaction::query()->where('gift_card_id', $giftCard->id)->where('type', GiftCardTransactionType::Adjustment)->sole()->amount)->toBe('-35.00');

    expect(fn () => $service->redeem($cancelled, '1.00', giftCardOrder()))
        ->toThrow(ValidationException::class);
});

test('a gift card cannot be redeemed against a different currency', function (): void {
    $service = app(GiftCardService::class);
    $giftCard = GiftCard::factory()->balance('50.00')->create(['currency' => 'NGN']);

    expect(fn () => $service->redeem($giftCard, '10.00', giftCardOrder('USD')))
        ->toThrow(ValidationException::class);
});
