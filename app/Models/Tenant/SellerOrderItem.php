<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line item within a seller sub-order.
 *
 * @property int $id
 * @property int $seller_order_id
 * @property int $order_item_id
 * @property int $quantity
 * @property string $unit_price
 * @property string $subtotal
 * @property string $total
 */
class SellerOrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_order_id',
        'order_item_id',
        'quantity',
        'unit_price',
        'subtotal',
        'total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_order_id' => 'integer',
            'order_item_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
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
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
