<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a POS sale is completed (fully paid).
 */
class POSSaleCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('order.'.$this->order->id),
        ];

        if ($this->order->pos_session_id !== null) {
            $channels[] = new PrivateChannel('pos.session.'.$this->order->pos_session_id);
        }

        if ($this->order->pos_terminal_id !== null) {
            $channels[] = new PrivateChannel('pos.terminal.'.$this->order->pos_terminal_id);
        }

        $tenantId = tenant('id');
        if ($tenantId !== null) {
            $channels[] = new PrivateChannel('tenant.'.$tenantId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'pos.sale.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'pos_terminal_id' => $this->order->pos_terminal_id,
            'pos_session_id' => $this->order->pos_session_id,
            'grand_total' => $this->order->grand_total,
            'payment_status' => $this->order->payment_status->value,
        ];
    }
}
