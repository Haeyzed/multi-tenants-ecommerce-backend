<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\FlashSale;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlashSaleEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FlashSale $flashSale) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        $tenantId = tenant('id');
        if ($tenantId !== null) {
            $channels[] = new PrivateChannel('tenant.'.$tenantId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'flash_sale.ended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'flash_sale_id' => $this->flashSale->id,
            'name' => $this->flashSale->name,
            'slug' => $this->flashSale->slug,
            'starts_at' => $this->flashSale->starts_at?->toIso8601String(),
            'ends_at' => $this->flashSale->ends_at?->toIso8601String(),
        ];
    }
}
