<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\FlashSaleItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlashSaleItemSoldOut implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FlashSaleItem $flashSaleItem) {}

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
        return 'flash_sale.sold_out';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'flash_sale_item_id' => $this->flashSaleItem->id,
            'flash_sale_id' => $this->flashSaleItem->flash_sale_id,
            'product_id' => $this->flashSaleItem->product_id,
            'product_variant_id' => $this->flashSaleItem->product_variant_id,
            'sold_qty' => $this->flashSaleItem->sold_qty,
            'qty_limit' => $this->flashSaleItem->qty_limit,
        ];
    }
}
