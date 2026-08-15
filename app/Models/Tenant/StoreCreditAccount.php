<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\StoreCreditAccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Single store credit wallet per customer.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $balance
 * @property string $currency
 * @property StoreCreditAccountStatus $status
 */
class StoreCreditAccount extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'balance',
        'currency',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'balance' => 'decimal:2',
            'status' => StoreCreditAccountStatus::class,
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
     * @return HasMany<StoreCreditTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(StoreCreditTransaction::class);
    }

    /**
     * Whether the wallet may be debited or credited.
     */
    public function isUsable(): bool
    {
        return $this->status === StoreCreditAccountStatus::Active;
    }
}
