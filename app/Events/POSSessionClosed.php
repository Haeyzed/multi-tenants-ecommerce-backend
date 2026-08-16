<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\PosSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a POS cashier session is closed.
 */
class POSSessionClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly PosSession $session) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('pos.session.'.$this->session->id),
            new PrivateChannel('pos.terminal.'.$this->session->pos_terminal_id),
        ];

        $tenantId = tenant('id');
        if ($tenantId !== null) {
            $channels[] = new PrivateChannel('tenant.'.$tenantId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'pos.session.closed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'pos_terminal_id' => $this->session->pos_terminal_id,
            'user_id' => $this->session->user_id,
            'status' => $this->session->status->value,
            'expected_cash' => $this->session->expected_cash,
            'actual_cash' => $this->session->actual_cash,
            'cash_difference' => $this->session->cash_difference,
        ];
    }
}
