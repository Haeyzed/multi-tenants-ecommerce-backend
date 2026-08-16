<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\DriverLocation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a driver's GPS position is updated (throttled by the location service).
 */
class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Driver $driver,
        public readonly Delivery $delivery,
        public readonly string $latitude,
        public readonly string $longitude,
        public readonly ?string $accuracy = null,
        public readonly ?string $heading = null,
        public readonly ?string $speed = null,
        public readonly ?DriverLocation $location = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('delivery.'.$this->delivery->id),
            new PrivateChannel('driver.'.$this->driver->id),
            new PrivateChannel('order.'.$this->delivery->order_id),
        ];

        $tenantId = tenant('id');
        if ($tenantId !== null) {
            $channels[] = new PrivateChannel('tenant.'.$tenantId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driver->id,
            'delivery_id' => $this->delivery->id,
            'order_id' => $this->delivery->order_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'heading' => $this->heading,
            'speed' => $this->speed,
            'location_id' => $this->location?->id,
            'recorded_at' => $this->location?->recorded_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
