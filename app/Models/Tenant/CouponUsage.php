<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Record of a coupon redemption.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $customer_id
 * @property int|null $order_id
 * @property string $discount_amount
 */
class CouponUsage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
        'discount_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coupon_id' => 'integer',
            'customer_id' => 'integer',
            'order_id' => 'integer',
            'discount_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
