<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\ReturnInspectionStatus;
use App\Enums\Tenant\Commerce\ReturnItemCondition;
use App\Enums\Tenant\Commerce\ReturnReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line item on an order return referencing an OrderItem.
 *
 * @property int $id
 * @property int $order_return_id
 * @property int $order_item_id
 * @property int $quantity
 * @property string $refund_amount
 */
class OrderReturnItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_return_id',
        'order_item_id',
        'quantity',
        'reason',
        'condition',
        'inspection_status',
        'inspection_note',
        'refund_amount',
        'inspected_by',
        'inspected_at',
        'restocked',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'inspection_status' => 'pending',
        'refund_amount' => '0.00',
        'restocked' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_return_id' => 'integer',
            'order_item_id' => 'integer',
            'quantity' => 'integer',
            'reason' => ReturnReason::class,
            'condition' => ReturnItemCondition::class,
            'inspection_status' => ReturnInspectionStatus::class,
            'refund_amount' => 'decimal:2',
            'inspected_by' => 'integer',
            'inspected_at' => 'datetime',
            'restocked' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
