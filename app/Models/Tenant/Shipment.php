<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Physical shipment for an order.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $shipping_method_id
 * @property string|null $tracking_number
 * @property string|null $carrier
 * @property string|null $tracking_url
 * @property ShipmentStatus $status
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property string|null $notes
 */
class Shipment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'shipping_method_id',
        'tracking_number',
        'carrier',
        'tracking_url',
        'status',
        'shipped_at',
        'delivered_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'shipping_method_id' => 'integer',
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
