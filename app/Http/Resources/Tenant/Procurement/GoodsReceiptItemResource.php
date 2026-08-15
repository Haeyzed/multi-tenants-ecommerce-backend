<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Procurement;

use App\Models\Tenant\GoodsReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceiptItem
 */
class GoodsReceiptItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GoodsReceiptItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'goods_receipt_id' => $item->goods_receipt_id,
            'purchase_order_item_id' => $item->purchase_order_item_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
        ];
    }
}
