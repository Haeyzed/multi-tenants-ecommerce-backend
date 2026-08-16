<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Point-in-time GPS fix for a driver on a delivery.
 *
 * @property int $id
 * @property int $driver_id
 * @property int $delivery_id
 * @property string $latitude
 * @property string $longitude
 * @property string|null $accuracy
 * @property string|null $heading
 * @property string|null $speed
 * @property Carbon $recorded_at
 */
class DriverLocation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver_id',
        'delivery_id',
        'latitude',
        'longitude',
        'accuracy',
        'heading',
        'speed',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver_id' => 'integer',
            'delivery_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'heading' => 'decimal:2',
            'speed' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<Delivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
