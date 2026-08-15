<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Marketplace\CommissionType;
use App\Enums\Tenant\Marketplace\SellerCommissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Platform commission accrued on a seller sub-order.
 *
 * @property int $id
 * @property int $seller_order_id
 * @property int $seller_id
 * @property int $order_id
 * @property CommissionType $commission_type
 * @property string|null $commission_rate
 * @property string|null $commission_fixed_amount
 * @property string $order_subtotal
 * @property string $commission_amount
 * @property string $seller_amount
 * @property SellerCommissionStatus $status
 * @property Carbon|null $earned_at
 */
class SellerCommission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_order_id',
        'seller_id',
        'order_id',
        'commission_type',
        'commission_rate',
        'commission_fixed_amount',
        'order_subtotal',
        'commission_amount',
        'seller_amount',
        'status',
        'earned_at',
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
            'seller_order_id' => 'integer',
            'seller_id' => 'integer',
            'order_id' => 'integer',
            'commission_type' => CommissionType::class,
            'commission_rate' => 'decimal:4',
            'commission_fixed_amount' => 'decimal:2',
            'order_subtotal' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'seller_amount' => 'decimal:2',
            'status' => SellerCommissionStatus::class,
            'earned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsToMany<SellerPayout, $this>
     */
    public function payouts(): BelongsToMany
    {
        return $this->belongsToMany(SellerPayout::class, 'seller_payout_commission');
    }
}
