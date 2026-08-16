<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Delivery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a delivery is assigned to a driver.
 */
class DeliveryAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Delivery $delivery,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('delivery.'.$this->delivery->id),
            new PrivateChannel('order.'.$this->delivery->order_id),
        ];

        if ($this->delivery->driver_id !== null) {
            $channels[] = new PrivateChannel('driver.'.$this->delivery->driver_id);
        }

        $tenantId = tenant('id');
        if ($tenantId !== null) {
            $channels[] = new PrivateChannel('tenant.'.$tenantId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'delivery.assigned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->delivery->id,
            'order_id' => $this->delivery->order_id,
            'driver_id' => $this->delivery->driver_id,
            'status' => $this->delivery->status->value,
        ];
    }
}
