<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Loyalty\LoyaltyTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only loyalty point ledger entry.
 *
 * @property int $id
 * @property int $loyalty_account_id
 * @property LoyaltyTransactionType $type
 * @property int $points Signed movement: positive earns, negative spends.
 * @property int $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 * @property array<string, mixed>|null $meta
 */
class LoyaltyTransaction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'loyalty_account_id',
        'type',
        'points',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loyalty_account_id' => 'integer',
            'type' => LoyaltyTransactionType::class,
            'points' => 'integer',
            'balance_after' => 'integer',
            'reference_id' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<LoyaltyAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
