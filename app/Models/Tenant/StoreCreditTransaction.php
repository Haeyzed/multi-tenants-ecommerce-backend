<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\StoreCreditTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable ledger entry for a store credit balance movement.
 *
 * Amounts are signed decimal strings: positive for credits and refunds,
 * negative for debits and expirations.
 *
 * @property int $id
 * @property int $store_credit_account_id
 * @property StoreCreditTransactionType $type
 * @property string $amount
 * @property string $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 */
class StoreCreditTransaction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_credit_account_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_credit_account_id' => 'integer',
            'type' => StoreCreditTransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'reference_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StoreCreditAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(StoreCreditAccount::class, 'store_credit_account_id');
    }

    /**
     * Subject that caused the movement (order, refund, return, ...).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
