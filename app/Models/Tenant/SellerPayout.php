<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Marketplace\SellerPayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Batch payout to a seller for earned commissions.
 *
 * @property int $id
 * @property int $seller_id
 * @property string $amount
 * @property string $currency
 * @property SellerPayoutStatus $status
 * @property string $idempotency_key
 * @property string|null $reference
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $metadata
 */
class SellerPayout extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'amount',
        'currency',
        'status',
        'idempotency_key',
        'reference',
        'paid_at',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => SellerPayoutStatus::class,
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return BelongsToMany<SellerCommission, $this>
     */
    public function commissions(): BelongsToMany
    {
        return $this->belongsToMany(SellerCommission::class, 'seller_payout_commission');
    }
}
