<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payment refund against an order.
 *
 * @property int $id
 * @property int $order_id
 * @property int $order_payment_id
 * @property string $amount
 * @property string $currency
 * @property string $reference
 * @property RefundStatus $status
 * @property string|null $reason
 * @property string|null $provider_refund_id
 * @property Carbon|null $processed_at
 * @property array<string, mixed>|null $metadata
 */
class Refund extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'order_payment_id',
        'amount',
        'currency',
        'reference',
        'status',
        'reason',
        'provider_refund_id',
        'processed_at',
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
            'order_id' => 'integer',
            'order_payment_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => RefundStatus::class,
            'processed_at' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<OrderPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }
}
