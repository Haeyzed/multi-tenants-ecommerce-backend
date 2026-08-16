<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\FlashSaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FlashSaleItem
 */
class FlashSaleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FlashSaleItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'flash_sale_id' => $item->flash_sale_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'sale_price' => $item->sale_price,
            'qty_limit' => $item->qty_limit,
            'sold_qty' => $item->sold_qty,
            'per_customer_limit' => $item->per_customer_limit,
            'customer_group_id' => $item->customer_group_id,
            'customer_segment_id' => $item->customer_segment_id,
            'remaining_quantity' => $item->remainingQuantity(),
            'is_sold_out' => $item->isSoldOut(),
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
