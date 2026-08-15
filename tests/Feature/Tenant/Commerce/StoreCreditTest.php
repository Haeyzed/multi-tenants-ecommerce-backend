<?php

declare(strict_types=1);

use App\Enums\Tenant\Commerce\StoreCreditAccountStatus;
use App\Enums\Tenant\Commerce\StoreCreditTransactionType;
use App\Models\Tenant\Customer;
use App\Models\Tenant\StoreCreditAccount;
use App\Models\Tenant\StoreCreditTransaction;
use App\Services\Tenant\Commerce\StoreCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notifications.sms.default' => 'null',
        'notifications.sms.enabled' => false,
        'notifications.queue' => false,
    ]);

    foreach (storeCreditMigrationFiles() as $file) {
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
function storeCreditMigrationFiles(): array
{
    return [
        '2026_08_15_041024_create_customers_table.php',
        '2026_08_15_100301_create_store_credit_accounts_table.php',
        '2026_08_15_100302_create_store_credit_transactions_table.php',
    ];
}

test('an account is created on first use with a zero balance', function (): void {
    $customer = Customer::factory()->create();

    $account = app(StoreCreditService::class)->getOrCreateAccount($customer);

    expect($account->balance)->toBe('0.00')
        ->and($account->currency)->toBe('NGN')
        ->and($account->status)->toBe(StoreCreditAccountStatus::Active)
        ->and(StoreCreditAccount::query()->where('customer_id', $customer->id)->count())->toBe(1);

    app(StoreCreditService::class)->getOrCreateAccount($customer);

    expect(StoreCreditAccount::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('crediting increases the balance and records a positive ledger entry', function (): void {
    $service = app(StoreCreditService::class);
    $customer = Customer::factory()->create();

    $transaction = $service->credit($customer, '150.00', StoreCreditTransactionType::Credit, 'Goodwill credit.');

    expect($transaction->type)->toBe(StoreCreditTransactionType::Credit)
        ->and($transaction->amount)->toBe('150.00')
        ->and($transaction->balance_after)->toBe('150.00')
        ->and($service->balance($customer))->toBe('150.00');

    $service->credit($customer, '25.50');

    expect($service->balance($customer))->toBe('175.50')
        ->and(StoreCreditTransaction::query()->count())->toBe(2);
});

test('debiting decreases the balance and records a negative ledger entry', function (): void {
    $service = app(StoreCreditService::class);
    $customer = Customer::factory()->create();

    $service->credit($customer, '200.00');
    $transaction = $service->debit($customer, '75.25', 'Applied to order ORD-1');

    expect($transaction->type)->toBe(StoreCreditTransactionType::Debit)
        ->and($transaction->amount)->toBe('-75.25')
        ->and($transaction->balance_after)->toBe('124.75')
        ->and($service->balance($customer))->toBe('124.75');
});

test('debiting more than the balance is rejected and leaves the balance untouched', function (): void {
    $service = app(StoreCreditService::class);
    $customer = Customer::factory()->create();

    $service->credit($customer, '40.00');

    expect(fn () => $service->debit($customer, '40.01'))->toThrow(ValidationException::class);

    expect($service->balance($customer))->toBe('40.00')
        ->and(StoreCreditTransaction::query()->where('type', StoreCreditTransactionType::Debit)->count())->toBe(0);
});

test('debiting an empty wallet is rejected', function (): void {
    $service = app(StoreCreditService::class);
    $customer = Customer::factory()->create();

    expect(fn () => $service->debit($customer, '1.00'))->toThrow(ValidationException::class);
});

test('balances are isolated per customer', function (): void {
    $service = app(StoreCreditService::class);
    $first = Customer::factory()->create();
    $second = Customer::factory()->create();

    $service->credit($first, '100.00');
    $service->credit($second, '10.00');
    $service->debit($first, '60.00');

    expect($service->balance($first))->toBe('40.00')
        ->and($service->balance($second))->toBe('10.00');

    $firstAccount = $service->getOrCreateAccount($first);
    $secondAccount = $service->getOrCreateAccount($second);

    expect(StoreCreditTransaction::query()->where('store_credit_account_id', $firstAccount->id)->count())->toBe(2)
        ->and(StoreCreditTransaction::query()->where('store_credit_account_id', $secondAccount->id)->count())->toBe(1);
});

test('a suspended wallet cannot be credited or debited', function (): void {
    $service = app(StoreCreditService::class);
    $customer = Customer::factory()->create();

    $service->credit($customer, '50.00');
    $service->updateStatus($customer, StoreCreditAccountStatus::Suspended);

    expect(fn () => $service->debit($customer, '10.00'))->toThrow(ValidationException::class)
        ->and(fn () => $service->credit($customer, '10.00'))->toThrow(ValidationException::class);
});

test('refund credit is recorded against the referencing model', function (): void {
    $service = app(StoreCreditService::class);
    $customer = Customer::factory()->create();

    $transaction = $service->creditFromRefund($customer, '80.00', $customer, 'Refunded as store credit.');

    expect($transaction->type)->toBe(StoreCreditTransactionType::Refund)
        ->and($transaction->amount)->toBe('80.00')
        ->and($transaction->reference_id)->toBe($customer->id)
        ->and($transaction->reference_type)->toBe($customer->getMorphClass())
        ->and($service->balance($customer))->toBe('80.00');
});

test('the applicable amount is capped by both the wallet balance and the amount due', function (): void {
    $service = app(StoreCreditService::class);
    $customer = Customer::factory()->create();

    $service->credit($customer, '100.00');

    expect($service->applicableAmount($customer, '150.00', '200.00'))->toBe('100.00')
        ->and($service->applicableAmount($customer, '50.00', '200.00'))->toBe('50.00')
        ->and($service->applicableAmount($customer, '80.00', '30.00'))->toBe('30.00');
});
