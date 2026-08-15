<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for an order line item.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'order_id' => $item->order_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'product_name' => $item->product_name,
            'sku' => $item->sku,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount_amount' => $item->discount_amount,
            'tax_amount' => $item->tax_amount,
            'subtotal' => $item->subtotal,
            'total' => $item->total,
            'inventory_id' => $item->inventory_id,
            'metadata' => $item->metadata,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
