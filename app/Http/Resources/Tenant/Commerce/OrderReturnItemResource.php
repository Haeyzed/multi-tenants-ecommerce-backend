<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\OrderReturnItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderReturnItem
 */
class OrderReturnItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderReturnItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'order_return_id' => $item->order_return_id,
            'order_item_id' => $item->order_item_id,
            'quantity' => $item->quantity,
            'reason' => $item->reason,
            'condition' => $item->condition,
            'inspection_status' => $item->inspection_status,
            'inspection_note' => $item->inspection_note,
            'refund_amount' => $item->refund_amount,
            'restocked' => $item->restocked,
            'inspected_at' => $item->inspected_at,
            'order_item' => $this->whenLoaded('orderItem', fn () => [
                'id' => $item->orderItem?->id,
                'product_name' => $item->orderItem?->product_name,
                'sku' => $item->orderItem?->sku,
                'quantity' => $item->orderItem?->quantity,
                'unit_price' => $item->orderItem?->unit_price,
            ]),
        ];
    }
}
