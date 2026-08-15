<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\StoreCreditAccountStatus;
use App\Enums\Tenant\Commerce\StoreCreditTransactionType;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\StoreCreditAccount;
use App\Models\Tenant\StoreCreditTransaction;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer store credit wallets and their ledger.
 *
 * Every balance change writes a signed {@see StoreCreditTransaction} so the account
 * balance can always be reconciled against the ledger.
 */
class StoreCreditService
{
    public function __construct(private readonly CommerceSettingService $commerceSettings) {}

    /**
     * Fetch the customer's wallet, creating an active one on first use.
     */
    public function getOrCreateAccount(Customer $customer, ?string $currency = null): StoreCreditAccount
    {
        $resolvedCurrency = Str::upper($currency ?? $this->commerceSettings->currencyCode());

        /** @var StoreCreditAccount $account */
        $account = StoreCreditAccount::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'balance' => '0.00',
                'currency' => $resolvedCurrency,
                'status' => StoreCreditAccountStatus::Active,
            ],
        );

        return $account;
    }

    /**
     * @param  array{status?: string|null, customer_id?: int|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, StoreCreditAccount>
     */
    public function listAccounts(array $params = []): LengthAwarePaginator
    {
        return StoreCreditAccount::query()
            ->with(['customer'])
            ->when($params['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($params['customer_id'] ?? null, fn ($query, int $customerId) => $query->where('customer_id', $customerId))
            ->latest('id')
            ->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    public function balance(Customer $customer, ?string $currency = null): string
    {
        return (string) $this->getOrCreateAccount($customer, $currency)->balance;
    }

    /**
     * @param  array{type?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, StoreCreditTransaction>
     */
    public function transactions(Customer $customer, array $params = []): LengthAwarePaginator
    {
        $account = $this->getOrCreateAccount($customer);

        return StoreCreditTransaction::query()
            ->where('store_credit_account_id', $account->id)
            ->when($params['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->latest('id')
            ->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * Add funds to a customer's wallet.
     *
     * @throws ValidationException
     */
    public function credit(
        Customer $customer,
        string $amount,
        StoreCreditTransactionType $type = StoreCreditTransactionType::Credit,
        ?string $description = null,
        ?Model $reference = null,
    ): StoreCreditTransaction {
        return DB::transaction(function () use ($customer, $amount, $type, $description, $reference): StoreCreditTransaction {
            $account = $this->lockAccount($customer);
            $this->assertUsable($account);

            $credited = Money::add($amount, '0');

            if (bccomp($credited, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Credit amount must be greater than zero.',
                ]);
            }

            $balanceAfter = Money::add((string) $account->balance, $credited);
            $account->balance = $balanceAfter;
            $account->save();

            return $this->recordTransaction($account, $type, $credited, $balanceAfter, $description, $reference);
        });
    }

    /**
     * Spend part of a customer's wallet, typically on a checkout.
     *
     * The account row is locked for the duration of the transaction so concurrent
     * checkouts cannot overspend the same balance.
     *
     * @throws ValidationException
     */
    public function debit(
        Customer $customer,
        string $amount,
        ?string $description = null,
        ?Model $reference = null,
    ): StoreCreditTransaction {
        return DB::transaction(function () use ($customer, $amount, $description, $reference): StoreCreditTransaction {
            $account = $this->lockAccount($customer);
            $this->assertUsable($account);

            $requested = Money::add($amount, '0');

            if (bccomp($requested, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'store_credit_amount' => 'Store credit amount must be greater than zero.',
                ]);
            }

            if (bccomp($requested, (string) $account->balance, 2) > 0) {
                throw ValidationException::withMessages([
                    'store_credit_amount' => 'Store credit balance is insufficient for this amount.',
                ]);
            }

            $balanceAfter = Money::sub((string) $account->balance, $requested);
            $account->balance = $balanceAfter;
            $account->save();

            return $this->recordTransaction(
                $account,
                StoreCreditTransactionType::Debit,
                Money::sub('0', $requested),
                $balanceAfter,
                $description,
                $reference,
            );
        });
    }

    /**
     * Issue store credit as the settlement for a refund.
     *
     * Used when the refund policy resolves to store credit rather than returning
     * funds to the original payment method.
     *
     * @throws ValidationException
     */
    public function creditFromRefund(
        Customer $customer,
        string $amount,
        ?Model $reference = null,
        ?string $description = null,
    ): StoreCreditTransaction {
        return $this->credit(
            $customer,
            $amount,
            StoreCreditTransactionType::Refund,
            $description ?? 'Refund issued as store credit.',
            $reference,
        );
    }

    /**
     * Return store credit that funded an order after a refund completes.
     *
     * @throws ValidationException
     */
    public function restoreForOrder(Order $order, string $amount): ?StoreCreditTransaction
    {
        $order->loadMissing('customer');
        $customer = $order->customer;

        if ($customer === null || bccomp($amount, '0', 2) <= 0) {
            return null;
        }

        return $this->credit(
            $customer,
            $amount,
            StoreCreditTransactionType::Refund,
            'Store credit restored for order '.$order->order_number,
            $order,
        );
    }

    /**
     * Amount of a due total this customer's wallet can cover.
     */
    public function applicableAmount(Customer $customer, string $requested, string $amountDue): string
    {
        $balance = $this->balance($customer);
        $applicable = bccomp($requested, $balance, 2) <= 0 ? $requested : $balance;

        return bccomp($applicable, $amountDue, 2) <= 0
            ? Money::add($applicable, '0')
            : Money::add($amountDue, '0');
    }

    /**
     * Manually adjust a wallet balance up or down.
     *
     * @throws ValidationException
     */
    public function adjust(Customer $customer, string $signedAmount, ?string $description = null): StoreCreditTransaction
    {
        return DB::transaction(function () use ($customer, $signedAmount, $description): StoreCreditTransaction {
            $account = $this->lockAccount($customer);
            $this->assertUsable($account);

            $delta = Money::add($signedAmount, '0');

            if (bccomp($delta, '0', 2) === 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment amount must not be zero.',
                ]);
            }

            $balanceAfter = Money::add((string) $account->balance, $delta);

            if (bccomp($balanceAfter, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment would take the store credit balance below zero.',
                ]);
            }

            $account->balance = $balanceAfter;
            $account->save();

            return $this->recordTransaction(
                $account,
                StoreCreditTransactionType::Adjustment,
                $delta,
                $balanceAfter,
                $description ?? 'Manual balance adjustment.',
                null,
            );
        });
    }

    /**
     * Change the wallet status (e.g. suspend abuse).
     */
    public function updateStatus(Customer $customer, StoreCreditAccountStatus $status): StoreCreditAccount
    {
        $account = $this->getOrCreateAccount($customer);
        $account->status = $status;
        $account->save();

        return $account->fresh() ?? $account;
    }

    protected function lockAccount(Customer $customer): StoreCreditAccount
    {
        $this->getOrCreateAccount($customer);

        /** @var StoreCreditAccount $account */
        $account = StoreCreditAccount::query()
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $account;
    }

    /**
     * @throws ValidationException
     */
    protected function assertUsable(StoreCreditAccount $account): void
    {
        if (! $account->isUsable()) {
            throw ValidationException::withMessages([
                'store_credit' => 'This store credit account is not active.',
            ]);
        }
    }

    protected function recordTransaction(
        StoreCreditAccount $account,
        StoreCreditTransactionType $type,
        string $amount,
        string $balanceAfter,
        ?string $description = null,
        ?Model $reference = null,
    ): StoreCreditTransaction {
        return StoreCreditTransaction::query()->create([
            'store_credit_account_id' => $account->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'description' => $description,
        ]);
    }
}
