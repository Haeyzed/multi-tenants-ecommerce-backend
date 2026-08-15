<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\GiftCardStatus;
use App\Enums\Tenant\Commerce\GiftCardTransactionType;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\GiftCardTransaction;
use App\Models\Tenant\Order;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issue, redeem and administer prepaid gift cards.
 *
 * Plain codes only exist in memory: they are returned once from {@see self::create()}
 * and hashed with SHA-256 before persistence. Lookups therefore always go through
 * {@see self::hashCode()} and never expose the secret again.
 */
class GiftCardService
{
    /**
     * Number of random characters in a generated code (excluding separators).
     */
    private const int CODE_LENGTH = 16;

    public function __construct(private readonly CommerceSettingService $commerceSettings) {}

    /**
     * @param  array{search?: string|null, status?: string|null, customer_id?: int|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, GiftCard>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return GiftCard::query()
            ->with(['customer'])
            ->filter($params)
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    public function show(GiftCard $giftCard): GiftCard
    {
        return $giftCard->load(['customer', 'transactions' => fn ($query) => $query->latest('id')]);
    }

    /**
     * Issue a new gift card and return it alongside the plain code.
     *
     * The plain code is the only copy that will ever exist; surface it once on the
     * create response and never persist it.
     *
     * @param  array{
     *     amount: string,
     *     currency?: string|null,
     *     expires_at?: string|null,
     *     activate?: bool|null,
     *     customer_id?: int|null,
     *     purchased_order_id?: int|null,
     *     meta?: array<string, mixed>|null
     * }  $data
     * @return array{0: GiftCard, 1: string}
     *
     * @throws ValidationException
     */
    public function create(array $data): array
    {
        $amount = Money::add((string) $data['amount'], '0');

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Gift card amount must be greater than zero.',
            ]);
        }

        $activate = (bool) ($data['activate'] ?? false);
        $plainCode = $this->generateCode();

        $giftCard = DB::transaction(function () use ($data, $amount, $activate, $plainCode): GiftCard {
            $giftCard = GiftCard::query()->create([
                'code_hash' => $this->hashCode($plainCode),
                'last_four' => substr(preg_replace('/[^A-Z0-9]/', '', $plainCode) ?? $plainCode, -4),
                'initial_amount' => $amount,
                'balance' => $amount,
                'currency' => Str::upper((string) ($data['currency'] ?? $this->commerceSettings->currencyCode())),
                'status' => $activate ? GiftCardStatus::Active : GiftCardStatus::Inactive,
                'expires_at' => $data['expires_at'] ?? null,
                'activated_at' => $activate ? Carbon::now() : null,
                'customer_id' => $data['customer_id'] ?? null,
                'purchased_order_id' => $data['purchased_order_id'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);

            $this->recordTransaction(
                $giftCard,
                GiftCardTransactionType::PurchaseActivate,
                $amount,
                (string) $giftCard->balance,
                null,
                $activate ? 'Gift card issued and activated.' : 'Gift card issued.',
            );

            return $giftCard;
        });

        return [$giftCard, $plainCode];
    }

    /**
     * Activate an inactive card so it can be redeemed.
     *
     * @throws ValidationException
     */
    public function activate(GiftCard $giftCard): GiftCard
    {
        if ($giftCard->status === GiftCardStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => 'A cancelled gift card cannot be activated.',
            ]);
        }

        if ($giftCard->isExpired()) {
            throw ValidationException::withMessages([
                'status' => 'An expired gift card cannot be activated.',
            ]);
        }

        $giftCard->status = GiftCardStatus::Active;
        $giftCard->activated_at ??= Carbon::now();
        $giftCard->save();

        return $giftCard->fresh() ?? $giftCard;
    }

    /**
     * Cancel a card, forfeiting any remaining balance.
     */
    public function cancel(GiftCard $giftCard, ?string $reason = null): GiftCard
    {
        return DB::transaction(function () use ($giftCard, $reason): GiftCard {
            /** @var GiftCard $locked */
            $locked = GiftCard::query()->whereKey($giftCard->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === GiftCardStatus::Cancelled) {
                return $locked;
            }

            $remaining = (string) $locked->balance;

            if (bccomp($remaining, '0', 2) > 0) {
                $this->recordTransaction(
                    $locked,
                    GiftCardTransactionType::Adjustment,
                    Money::sub('0', $remaining),
                    '0.00',
                    null,
                    $reason ?? 'Gift card cancelled.',
                );
            }

            $locked->balance = '0.00';
            $locked->status = GiftCardStatus::Cancelled;
            $locked->save();

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Look up a card by its plain code.
     */
    public function findByCode(string $plainCode): ?GiftCard
    {
        $normalized = $this->normalizeCode($plainCode);

        if ($normalized === '') {
            return null;
        }

        return GiftCard::query()->where('code_hash', $this->hashCode($normalized))->first();
    }

    /**
     * Resolve a redeemable card for the given currency, or fail validation.
     *
     * @throws ValidationException
     */
    public function resolveRedeemable(string $plainCode, string $currency): GiftCard
    {
        $giftCard = $this->findByCode($plainCode);

        if ($giftCard === null) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'The gift card code is invalid.',
            ]);
        }

        $this->markExpiredIfDue($giftCard);
        $this->assertRedeemable($giftCard, $currency);

        return $giftCard;
    }

    /**
     * Flip a lapsed card to the expired status.
     *
     * Kept separate from {@see self::assertRedeemable()} so the housekeeping write
     * survives even when the caller's transaction rolls back on a rejected redemption.
     */
    public function markExpiredIfDue(GiftCard $giftCard): void
    {
        if ($giftCard->isExpired() && $giftCard->status !== GiftCardStatus::Expired) {
            $giftCard->status = GiftCardStatus::Expired;
            $giftCard->save();
        }
    }

    /**
     * Amount of a due total this card can cover.
     */
    public function applicableAmount(GiftCard $giftCard, string $amountDue): string
    {
        $balance = (string) $giftCard->balance;

        return bccomp($balance, $amountDue, 2) >= 0
            ? Money::add($amountDue, '0')
            : Money::add($balance, '0');
    }

    /**
     * Redeem part or all of a card's balance against an order.
     *
     * The card row is locked for the duration of the transaction so concurrent
     * checkouts cannot overspend the same balance. A card that reaches a zero
     * balance is marked depleted.
     *
     * @throws ValidationException
     */
    public function redeem(GiftCard|string $giftCard, string $amount, Order $order): GiftCardTransaction
    {
        return DB::transaction(function () use ($giftCard, $amount, $order): GiftCardTransaction {
            $locked = $this->lockCard($giftCard);

            $this->assertRedeemable($locked, $order->currency);

            $requested = Money::add($amount, '0');

            if (bccomp($requested, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Redemption amount must be greater than zero.',
                ]);
            }

            if (bccomp($requested, (string) $locked->balance, 2) > 0) {
                throw ValidationException::withMessages([
                    'gift_card_code' => 'The gift card balance is insufficient for this amount.',
                ]);
            }

            $balanceAfter = Money::sub((string) $locked->balance, $requested);

            $locked->balance = $balanceAfter;

            if (bccomp($balanceAfter, '0', 2) === 0) {
                $locked->status = GiftCardStatus::Depleted;
            }

            $locked->save();

            return $this->recordTransaction(
                $locked,
                GiftCardTransactionType::Redeem,
                Money::sub('0', $requested),
                $balanceAfter,
                $order,
                'Redeemed on order '.$order->order_number,
            );
        });
    }

    /**
     * Return a previously redeemed amount to the originating card.
     *
     * Called when a refund completes for the gift-card-funded portion of an order:
     * the balance goes back to the same card it was redeemed from, and a depleted
     * card becomes active again unless it expired or was cancelled meanwhile.
     *
     * @throws ValidationException
     */
    public function restoreFromRefund(GiftCard|string $giftCard, string $amount, ?Order $order = null, ?string $description = null): GiftCardTransaction
    {
        return DB::transaction(function () use ($giftCard, $amount, $order, $description): GiftCardTransaction {
            $locked = $this->lockCard($giftCard);

            $restored = Money::add($amount, '0');

            if (bccomp($restored, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Restore amount must be greater than zero.',
                ]);
            }

            $balanceAfter = Money::add((string) $locked->balance, $restored);
            $locked->balance = $balanceAfter;

            if ($locked->status === GiftCardStatus::Depleted && ! $locked->isExpired()) {
                $locked->status = GiftCardStatus::Active;
            }

            $locked->save();

            return $this->recordTransaction(
                $locked,
                GiftCardTransactionType::RefundRestore,
                $restored,
                $balanceAfter,
                $order,
                $description ?? 'Refund restored to gift card.',
            );
        });
    }

    /**
     * Manually adjust a card balance up or down.
     *
     * @throws ValidationException
     */
    public function adjust(GiftCard $giftCard, string $signedAmount, ?string $description = null): GiftCardTransaction
    {
        return DB::transaction(function () use ($giftCard, $signedAmount, $description): GiftCardTransaction {
            $locked = $this->lockCard($giftCard);
            $delta = Money::add($signedAmount, '0');

            if (bccomp($delta, '0', 2) === 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment amount must not be zero.',
                ]);
            }

            $balanceAfter = Money::add((string) $locked->balance, $delta);

            if (bccomp($balanceAfter, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment would take the gift card balance below zero.',
                ]);
            }

            $locked->balance = $balanceAfter;

            if (bccomp($balanceAfter, '0', 2) === 0 && $locked->status === GiftCardStatus::Active) {
                $locked->status = GiftCardStatus::Depleted;
            }

            $locked->save();

            return $this->recordTransaction(
                $locked,
                GiftCardTransactionType::Adjustment,
                $delta,
                $balanceAfter,
                null,
                $description ?? 'Manual balance adjustment.',
            );
        });
    }

    /**
     * Masked balance preview for a customer-supplied code.
     *
     * @return array{last_four: string, currency: string, balance: string, applicable_amount: string, expires_at: Carbon|null}
     *
     * @throws ValidationException
     */
    public function preview(string $plainCode, string $currency, string $amountDue): array
    {
        $giftCard = $this->resolveRedeemable($plainCode, $currency);

        return [
            'last_four' => $giftCard->last_four,
            'currency' => $giftCard->currency,
            'balance' => (string) $giftCard->balance,
            'applicable_amount' => $this->applicableAmount($giftCard, Money::add($amountDue, '0')),
            'expires_at' => $giftCard->expires_at,
        ];
    }

    /**
     * Hash a plain code for storage and lookup.
     */
    public function hashCode(string $plainCode): string
    {
        return hash('sha256', $this->normalizeCode($plainCode));
    }

    /**
     * Reject cards that cannot currently fund a checkout.
     *
     * @throws ValidationException
     */
    protected function assertRedeemable(GiftCard $giftCard, string $currency): void
    {
        if ($giftCard->isExpired()) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'This gift card has expired.',
            ]);
        }

        if ($giftCard->status !== GiftCardStatus::Active) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'This gift card is not active.',
            ]);
        }

        if (Str::upper($giftCard->currency) !== Str::upper($currency)) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'This gift card cannot be used for this currency.',
            ]);
        }

        if (bccomp((string) $giftCard->balance, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'This gift card has no remaining balance.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function lockCard(GiftCard|string $giftCard): GiftCard
    {
        $query = GiftCard::query()->lockForUpdate();

        $locked = $giftCard instanceof GiftCard
            ? $query->whereKey($giftCard->getKey())->first()
            : $query->where('code_hash', $this->hashCode($giftCard))->first();

        if ($locked === null) {
            throw ValidationException::withMessages([
                'gift_card_code' => 'The gift card code is invalid.',
            ]);
        }

        return $locked;
    }

    protected function recordTransaction(
        GiftCard $giftCard,
        GiftCardTransactionType $type,
        string $amount,
        string $balanceAfter,
        ?Order $order = null,
        ?string $description = null,
    ): GiftCardTransaction {
        return GiftCardTransaction::query()->create([
            'gift_card_id' => $giftCard->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'order_id' => $order?->id,
            'description' => $description,
        ]);
    }

    /**
     * Generate a human-readable code in GC-XXXX-XXXX-XXXX-XXXX form.
     */
    protected function generateCode(): string
    {
        do {
            $body = Str::upper(Str::random(self::CODE_LENGTH));
            $code = 'GC-'.implode('-', str_split($body, 4));
        } while (GiftCard::query()->where('code_hash', $this->hashCode($code))->exists());

        return $code;
    }

    protected function normalizeCode(string $plainCode): string
    {
        return Str::upper(trim($plainCode));
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
