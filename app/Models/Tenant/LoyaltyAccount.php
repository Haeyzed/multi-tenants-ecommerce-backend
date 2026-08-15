<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Loyalty\LoyaltyAccountStatus;
use Database\Factories\Tenant\LoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer loyalty account holding the cached point balance.
 *
 * The balance is a cache of the ledger and must only be mutated by
 * App\Services\Tenant\Loyalty\LoyaltyService while the row is locked.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $balance
 * @property int $lifetime_earned
 * @property int $lifetime_redeemed
 * @property LoyaltyAccountStatus $status
 */
class LoyaltyAccount extends Model
{
    /** @use HasFactory<LoyaltyAccountFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'balance',
        'lifetime_earned',
        'lifetime_redeemed',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'balance' => 0,
        'lifetime_earned' => 0,
        'lifetime_redeemed' => 0,
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'balance' => 'integer',
            'lifetime_earned' => 'integer',
            'lifetime_redeemed' => 'integer',
            'status' => LoyaltyAccountStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<LoyaltyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /**
     * Whether the account may earn or redeem points.
     */
    public function isActive(): bool
    {
        return $this->status === LoyaltyAccountStatus::Active;
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{search?: string|null, status?: string|null, customer_id?: int|null}  $params
     * @return Builder<$this>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when(array_key_exists('customer_id', $params) && $params['customer_id'] !== null, function (Builder $query) use ($params): void {
                $query->where('customer_id', (int) $params['customer_id']);
            })
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->whereHas('customer', function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            });
    }
}
