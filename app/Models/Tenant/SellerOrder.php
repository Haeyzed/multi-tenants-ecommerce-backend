<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Seller-scoped slice of a customer order.
 *
 * @property int $id
 * @property int $order_id
 * @property int $seller_id
 * @property SellerOrderStatus $status
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $shipping_total
 * @property string $commission_total
 * @property string $seller_total
 * @property Carbon|null $fulfilled_at
 */
class SellerOrder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'seller_id',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'commission_total',
        'seller_total',
        'fulfilled_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'discount_total' => 0,
        'tax_total' => 0,
        'shipping_total' => 0,
        'commission_total' => 0,
        'seller_total' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'seller_id' => 'integer',
            'status' => SellerOrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'commission_total' => 'decimal:2',
            'seller_total' => 'decimal:2',
            'fulfilled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return HasMany<SellerOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SellerOrderItem::class);
    }

    /**
     * @return HasOne<SellerCommission, $this>
     */
    public function commission(): HasOne
    {
        return $this->hasOne(SellerCommission::class);
    }
}
