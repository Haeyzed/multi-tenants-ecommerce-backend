<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Procurement;

use App\Models\Tenant\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderItem
 */
class PurchaseOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrderItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'purchase_order_id' => $item->purchase_order_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'received_quantity' => $item->received_quantity,
            'unit_cost' => $item->unit_cost,
            'tax' => $item->tax,
            'total' => $item->total,
        ];
    }
}
