<?php

declare(strict_types=1);

namespace App\Services\Tenant\Loyalty;

use App\Enums\Tenant\Loyalty\LoyaltyAccountStatus;
use App\Enums\Tenant\Loyalty\LoyaltyTransactionType;
use App\Events\LoyaltyPointsEarned;
use App\Events\LoyaltyPointsRedeemed;
use App\Models\Tenant\Customer;
use App\Models\Tenant\LoyaltyAccount;
use App\Models\Tenant\LoyaltyProgram;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\Order;
use App\Models\Tenant\Refund;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Loyalty program settings, accounts and the point ledger.
 *
 * Every balance change goes through recordTransaction(), which locks the
 * account row so the cached balance can never drift from the ledger.
 */
class LoyaltyService
{
    /**
     * Create a new class instance.
     *
     * @param  CommerceSettingService  $commerceSettings
     */
    public function __construct(private readonly CommerceSettingService $commerceSettings) {}

    /**
     * The active loyalty program, or null when loyalty is not configured for this tenant.
     *
     * @return ?LoyaltyProgram
     */
    public function program(): ?LoyaltyProgram
    {
        if (! $this->isAvailable()) {
            return null;
        }

        return LoyaltyProgram::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Resolve the single program row, creating it from commerce settings when missing.
     *
     * @return LoyaltyProgram
     */
    public function ensureProgram(): LoyaltyProgram
    {
        $program = LoyaltyProgram::query()
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();

        if ($program !== null) {
            return $program;
        }

        return LoyaltyProgram::query()->create([
            'name' => $this->commerceSettings->get('loyalty.name', 'Loyalty Rewards') ?? 'Loyalty Rewards',
            'is_active' => $this->commerceSettings->loyaltyIsActive(),
            'points_per_currency_unit' => $this->commerceSettings->loyaltyPointsPerCurrencyUnit(),
            'redemption_points_per_currency' => $this->commerceSettings->loyaltyRedemptionPointsPerCurrency(),
            'min_redemption_points' => $this->commerceSettings->loyaltyMinRedemptionPoints(),
            'max_redemption_percent' => $this->commerceSettings->loyaltyMaxRedemptionPercent(),
            'earn_on_order_paid' => $this->commerceSettings->loyaltyEarnOnOrderPaid(),
        ]);
    }

    /**
     * Update the program settings.
     *
     * @param  array<string, mixed>  $data
     * @return LoyaltyProgram
     */
    public function updateProgram(array $data): LoyaltyProgram
    {
        $program = $this->ensureProgram();

        $program->fill(array_intersect_key($data, array_flip([
            'name',
            'is_active',
            'points_per_currency_unit',
            'redemption_points_per_currency',
            'min_redemption_points',
            'max_redemption_percent',
            'earn_on_order_paid',
        ])));

        $program->save();

        return $program;
    }

    /**
     * Get the customer's loyalty account, creating an empty one on first use.
     *
     * @param  Customer  $customer
     * @return LoyaltyAccount
     */
    public function getOrCreateAccount(Customer $customer): LoyaltyAccount
    {
        /** @var LoyaltyAccount $account */
        $account = LoyaltyAccount::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['status' => LoyaltyAccountStatus::Active],
        );

        return $account;
    }

    /**
     * List accounts.
     *
     * @param  array{search?: string|null, status?: string|null, customer_id?: int|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, LoyaltyAccount>
     */
    public function listAccounts(array $params = []): LengthAwarePaginator
    {
        return LoyaltyAccount::query()
            ->with('customer')
            ->filter($params)
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * List transactions.
     *
     * @param  LoyaltyAccount  $account
     * @param  array{type?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, LoyaltyTransaction>
     */
    public function listTransactions(LoyaltyAccount $account, array $params = []): LengthAwarePaginator
    {
        return $account->transactions()
            ->when($params['type'] ?? null, function ($query, string $type): void {
                $query->where('type', $type);
            })
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * Award points for an order that has just been paid.
     *
     * @param  Order  $order
     * @return ?LoyaltyTransaction
     */
    public function earnForPaidOrder(Order $order): ?LoyaltyTransaction
    {
        $program = $this->program();

        if ($program === null || ! $program->earn_on_order_paid) {
            return null;
        }

        if ($this->hasTransactionFor(LoyaltyTransactionType::Earn, $order)) {
            return null;
        }

        $customer = $order->customer ?? Customer::query()->find($order->customer_id);

        if ($customer === null) {
            return null;
        }

        $earnableAmount = Money::sub((string) $order->subtotal, (string) $order->discount_total);
        $points = $this->pointsForAmount($program, $earnableAmount);

        if ($points <= 0) {
            return null;
        }

        $account = $this->getOrCreateAccount($customer);

        if (! $account->isActive()) {
            return null;
        }

        $transaction = $this->recordTransaction(
            $account,
            LoyaltyTransactionType::Earn,
            $points,
            $order,
            'Points earned for order '.$order->order_number,
            ['earnable_amount' => $earnableAmount],
        );

        if (Schema::hasColumn('orders', 'loyalty_points_earned')) {
            $order->forceFill(['loyalty_points_earned' => $points])->save();
        }

        $account = $account->fresh() ?? $account;
        event(new LoyaltyPointsEarned($account, $transaction, $order));

        return $transaction;
    }

    /**
     * Calculate what a point redemption is worth without touching the ledger.
     *
     * @param  Customer  $customer
     * @param  int  $points
     * @param  string  $orderSubtotal
     * @param  ?string  $maxDiscountAmount
     * @return LoyaltyRedemptionResult
     *
     * @throws ValidationException
     */
    public function previewRedemption(
        Customer $customer,
        int $points,
        string $orderSubtotal,
        ?string $maxDiscountAmount = null,
    ): LoyaltyRedemptionResult {
        $program = $this->program();

        if ($program === null) {
            throw ValidationException::withMessages([
                'loyalty_points' => 'Loyalty rewards are not available.',
            ]);
        }

        if ($points <= 0) {
            return LoyaltyRedemptionResult::none();
        }

        $account = $this->getOrCreateAccount($customer);

        if (! $account->isActive()) {
            throw ValidationException::withMessages([
                'loyalty_points' => 'This loyalty account is suspended.',
            ]);
        }

        if ($points < $program->min_redemption_points) {
            throw ValidationException::withMessages([
                'loyalty_points' => 'You must redeem at least '.$program->min_redemption_points.' points.',
            ]);
        }

        if ($points > $account->balance) {
            throw ValidationException::withMessages([
                'loyalty_points' => 'Insufficient loyalty points balance.',
            ]);
        }

        $spendable = Money::percent($orderSubtotal, (string) $program->max_redemption_percent);

        if ($maxDiscountAmount !== null && bccomp($maxDiscountAmount, $spendable, 2) < 0) {
            $spendable = Money::add($maxDiscountAmount, '0');
        }

        if (bccomp($spendable, '0', 2) <= 0) {
            return LoyaltyRedemptionResult::none();
        }

        $spendablePoints = (int) bcmul($spendable, (string) $program->redemption_points_per_currency, 0);
        $effectivePoints = min($points, $spendablePoints);

        if ($effectivePoints <= 0) {
            return LoyaltyRedemptionResult::none();
        }

        return new LoyaltyRedemptionResult(
            points: $effectivePoints,
            moneyValue: bcdiv((string) $effectivePoints, (string) $program->redemption_points_per_currency, 2),
        );
    }

    /**
     * Spend points for a checkout, writing the redeem entry to the ledger.
     *
     * @param  Customer  $customer
     * @param  int  $points
     * @param  string  $orderSubtotal
     * @param  ?Order  $order
     * @return LoyaltyRedemptionResult
     *
     * @throws ValidationException
     */
    public function redeemForCheckout(
        Customer $customer,
        int $points,
        string $orderSubtotal,
        ?Order $order = null,
    ): LoyaltyRedemptionResult {
        $result = $this->previewRedemption($customer, $points, $orderSubtotal);

        if (! $result->isRedeemable()) {
            return $result;
        }

        $account = $this->getOrCreateAccount($customer);

        $transaction = $this->recordTransaction(
            $account,
            LoyaltyTransactionType::Redeem,
            -$result->points,
            $order,
            $order !== null
                ? 'Points redeemed on order '.$order->order_number
                : 'Points redeemed at checkout',
            ['money_value' => $result->moneyValue],
        );

        $account = $account->fresh() ?? $account;
        event(new LoyaltyPointsRedeemed($account, $transaction, $order));

        return $result;
    }

    /**
     * Claw back the share of earned points covered by a refund.
     *
     * @param  Order  $order
     * @param  Refund  $refund
     * @return ?LoyaltyTransaction
     */
    public function reverseEarnForRefund(Order $order, Refund $refund): ?LoyaltyTransaction
    {
        if (! $this->isAvailable()) {
            return null;
        }

        if ($this->hasTransactionFor(LoyaltyTransactionType::RefundReversal, $refund)) {
            return null;
        }

        $earned = (int) $this->transactionsFor($order)
            ->where('type', LoyaltyTransactionType::Earn)
            ->sum('points');

        if ($earned <= 0) {
            return null;
        }

        $alreadyReversed = abs((int) $this->transactionsFor($order)
            ->where('type', LoyaltyTransactionType::RefundReversal)
            ->sum('points'));

        $reversible = $earned - $alreadyReversed;

        if ($reversible <= 0) {
            return null;
        }

        $grandTotal = (string) $order->grand_total;
        $points = bccomp($grandTotal, '0', 2) > 0
            ? (int) bcdiv(bcmul((string) $earned, (string) $refund->amount, 4), $grandTotal, 0)
            : $earned;

        $customer = $order->customer ?? Customer::query()->find($order->customer_id);

        if ($customer === null) {
            return null;
        }

        $account = $this->getOrCreateAccount($customer);
        $points = min($points, $reversible, $account->balance);

        if ($points <= 0) {
            return null;
        }

        return $this->recordTransaction(
            $account,
            LoyaltyTransactionType::RefundReversal,
            -$points,
            $refund,
            'Points reversed for refund on order '.$order->order_number,
            ['order_id' => $order->id, 'refund_amount' => (string) $refund->amount],
        );
    }

    /**
     * Apply a manual staff adjustment to an account.
     *
     * @param  LoyaltyAccount  $account
     * @param  int  $points
     * @param  ?string  $description
     * @return LoyaltyTransaction
     *
     * @throws ValidationException
     */
    public function adjust(LoyaltyAccount $account, int $points, ?string $description = null): LoyaltyTransaction
    {
        if ($points === 0) {
            throw ValidationException::withMessages([
                'points' => 'Adjustment points must not be zero.',
            ]);
        }

        return $this->recordTransaction(
            $account,
            LoyaltyTransactionType::Adjustment,
            $points,
            null,
            $description ?? 'Manual adjustment',
        );
    }

    /**
     * Whether the loyalty tables have been migrated for this tenant.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return Schema::hasTable('loyalty_programs')
            && Schema::hasTable('loyalty_accounts')
            && Schema::hasTable('loyalty_transactions');
    }

    /**
     * Points awarded for a monetary amount, truncated to a whole number.
     *
     * @param  LoyaltyProgram  $program
     * @param  string  $amount
     * @return int
     */
    protected function pointsForAmount(LoyaltyProgram $program, string $amount): int
    {
        if (bccomp($amount, '0', 2) <= 0) {
            return 0;
        }

        return (int) bcmul($amount, (string) $program->points_per_currency_unit, 0);
    }

    /**
     * The only writer of account balances: locks the account, appends a ledger entry, then rewrites the cached totals from the movement.
     *
     * @param  LoyaltyAccount  $account
     * @param  LoyaltyTransactionType  $type
     * @param  int  $points
     * @param  ?Model  $reference
     * @param  ?string  $description
     * @param  array<string, mixed>|null  $meta
     * @return LoyaltyTransaction
     *
     * @throws ValidationException
     */
    protected function recordTransaction(
        LoyaltyAccount $account,
        LoyaltyTransactionType $type,
        int $points,
        ?Model $reference = null,
        ?string $description = null,
        ?array $meta = null,
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($account, $type, $points, $reference, $description, $meta): LoyaltyTransaction {
            /** @var LoyaltyAccount $locked */
            $locked = LoyaltyAccount::query()
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $balanceAfter = $locked->balance + $points;

            if ($balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'loyalty_points' => 'Insufficient loyalty points balance.',
                ]);
            }

            /** @var LoyaltyTransaction $transaction */
            $transaction = $locked->transactions()->create([
                'type' => $type,
                'points' => $points,
                'balance_after' => $balanceAfter,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'meta' => $meta,
            ]);

            $locked->balance = $balanceAfter;

            if ($points > 0) {
                $locked->lifetime_earned += $points;
            }

            if ($points < 0 && $type === LoyaltyTransactionType::Redeem) {
                $locked->lifetime_redeemed += abs($points);
            }

            if ($points < 0 && $type === LoyaltyTransactionType::RefundReversal) {
                $locked->lifetime_earned = max(0, $locked->lifetime_earned - abs($points));
            }

            $locked->save();

            $account->setRawAttributes($locked->getAttributes(), true);

            return $transaction;
        });
    }

    /**
     * Ledger entries referencing a given model.
     *
     * @param  Model  $reference
     * @return Builder<LoyaltyTransaction>
     */
    protected function transactionsFor(Model $reference): Builder
    {
        return LoyaltyTransaction::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey());
    }

    /**
     * Has transaction for.
     *
     * @param  LoyaltyTransactionType  $type
     * @param  Model  $reference
     * @return bool
     */
    protected function hasTransactionFor(LoyaltyTransactionType $type, Model $reference): bool
    {
        return $this->transactionsFor($reference)->where('type', $type)->exists();
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
