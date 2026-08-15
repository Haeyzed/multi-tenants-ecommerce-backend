<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payment / gateway transaction against an order.
 *
 * @property int $id
 * @property int $order_id
 * @property int $customer_id
 * @property string $amount
 * @property string $currency
 * @property string $gateway
 * @property string $reference
 * @property string|null $provider_transaction_id
 * @property OrderPaymentRecordStatus $status
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property array<string, mixed>|null $metadata
 */
class OrderPayment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'customer_id',
        'amount',
        'currency',
        'gateway',
        'reference',
        'provider_transaction_id',
        'status',
        'paid_at',
        'failed_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'customer_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => OrderPaymentRecordStatus::class,
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
