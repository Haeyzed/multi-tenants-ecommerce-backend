<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\Tenant\SellerOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerOrderItem
 */
class SellerOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SellerOrderItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'seller_order_id' => $item->seller_order_id,
            'order_item_id' => $item->order_item_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'subtotal' => $item->subtotal,
            'total' => $item->total,
        ];
    }
}
