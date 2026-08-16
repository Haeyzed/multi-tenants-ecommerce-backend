<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\GiftCardStatus;
use Database\Factories\Tenant\GiftCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Prepaid gift card redeemable at checkout.
 *
 * The plain code is never persisted: only its SHA-256 hash and the last four
 * characters are stored so cards can be looked up without exposing the secret.
 *
 * @property int $id
 * @property string $code_hash
 * @property string $last_four
 * @property string $initial_amount
 * @property string $balance
 * @property string $currency
 * @property GiftCardStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $activated_at
 * @property int|null $customer_id
 * @property int|null $purchased_order_id
 * @property array<string, mixed>|null $meta
 */
class GiftCard extends Model
{
    /** @use HasFactory<GiftCardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code_hash',
        'last_four',
        'initial_amount',
        'balance',
        'currency',
        'status',
        'expires_at',
        'activated_at',
        'customer_id',
        'purchased_order_id',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'status' => GiftCardStatus::class,
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'customer_id' => 'integer',
            'purchased_order_id' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return HasMany<GiftCardTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }

    /**
     * Purchaser or assigned owner of the card.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Order the card was sold on, when it originated from a store purchase.
     *
     * @return BelongsTo<Order, $this>
     */
    public function purchasedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'purchased_order_id');
    }

    /**
     * Whether the card has passed its expiry date.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the card may currently fund a checkout.
     */
    public function isRedeemable(): bool
    {
        return $this->status === GiftCardStatus::Active
            && ! $this->isExpired()
            && bccomp((string) $this->balance, '0', 2) > 0;
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{search?: string|null, status?: string|null, customer_id?: int|null}  $params
     * @return Builder<$this>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('last_four', 'like', '%'.$search.'%');
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['customer_id'] ?? null, function (Builder $query, int $customerId): void {
                $query->where('customer_id', $customerId);
            });
    }
}
