<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Procurement;

use App\Models\Tenant\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceipt
 */
class GoodsReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GoodsReceipt $receipt */
        $receipt = $this->resource;

        return [
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'purchase_order_id' => $receipt->purchase_order_id,
            'warehouse_id' => $receipt->warehouse_id,
            'received_at' => $receipt->received_at,
            'notes' => $receipt->notes,
            'received_by' => $receipt->received_by,
            'items' => GoodsReceiptItemResource::collection($this->whenLoaded('items')),
            'created_at' => $receipt->created_at,
            'updated_at' => $receipt->updated_at,
        ];
    }
}
