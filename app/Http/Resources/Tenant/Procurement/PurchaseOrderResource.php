<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Procurement;

use App\Models\Tenant\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrder $po */
        $po = $this->resource;

        return [
            'id' => $po->id,
            'order_number' => $po->order_number,
            'supplier_id' => $po->supplier_id,
            'warehouse_id' => $po->warehouse_id,
            'currency' => $po->currency,
            'status' => $po->status,
            'subtotal' => $po->subtotal,
            'tax_total' => $po->tax_total,
            'discount_total' => $po->discount_total,
            'shipping_total' => $po->shipping_total,
            'grand_total' => $po->grand_total,
            'ordered_at' => $po->ordered_at,
            'expected_at' => $po->expected_at,
            'notes' => $po->notes,
            'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($po->supplier)),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $po->created_at,
            'updated_at' => $po->updated_at,
        ];
    }
}
