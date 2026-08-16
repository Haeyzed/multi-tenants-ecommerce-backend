<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Delivery\DeliveryStatus;
use Database\Factories\Tenant\DeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Driver delivery assignment for an order (and optional shipment).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $shipment_id
 * @property int|null $driver_id
 * @property DeliveryStatus $status
 * @property Carbon|null $assigned_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $picked_up_at
 * @property Carbon|null $out_for_delivery_at
 * @property Carbon|null $arrived_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $failure_reason
 * @property string|null $notes
 */
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'shipment_id',
        'driver_id',
        'status',
        'assigned_at',
        'accepted_at',
        'rejected_at',
        'picked_up_at',
        'out_for_delivery_at',
        'arrived_at',
        'delivered_at',
        'failed_at',
        'cancelled_at',
        'failure_reason',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'shipment_id' => 'integer',
            'driver_id' => 'integer',
            'status' => DeliveryStatus::class,
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'arrived_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return HasMany<DriverLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(DriverLocation::class);
    }

    /**
     * Whether the delivery is actively in progress with an assigned driver.
     */
    public function isActiveForDriver(): bool
    {
        return $this->driver_id !== null
            && in_array($this->status, [
                DeliveryStatus::Accepted,
                DeliveryStatus::PickedUp,
                DeliveryStatus::OutForDelivery,
                DeliveryStatus::Arrived,
            ], true);
    }
}
