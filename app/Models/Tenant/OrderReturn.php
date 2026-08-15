<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\ReturnReason;
use App\Enums\Tenant\Commerce\ReturnStatus;
use Database\Factories\Tenant\OrderReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer return merchandise authorization for an order.
 *
 * @property int $id
 * @property string $return_number
 * @property int $order_id
 * @property int $customer_id
 * @property int|null $seller_id
 * @property ReturnStatus $status
 * @property ReturnReason|null $reason
 */
class OrderReturn extends Model
{
    /** @use HasFactory<OrderReturnFactory> */
    use HasFactory;

    protected $table = 'order_returns';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'return_number',
        'order_id',
        'customer_id',
        'seller_id',
        'status',
        'reason',
        'customer_note',
        'admin_note',
        'refund_id',
        'requested_at',
        'approved_at',
        'rejected_at',
        'received_at',
        'completed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'requested',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'customer_id' => 'integer',
            'seller_id' => 'integer',
            'refund_id' => 'integer',
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return HasMany<OrderReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class, 'order_return_id');
    }
}
