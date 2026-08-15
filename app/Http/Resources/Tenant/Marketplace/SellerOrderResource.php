<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\Tenant\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerOrder
 */
class SellerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SellerOrder $sellerOrder */
        $sellerOrder = $this->resource;

        return [
            'id' => $sellerOrder->id,
            'order_id' => $sellerOrder->order_id,
            'seller_id' => $sellerOrder->seller_id,
            'status' => $sellerOrder->status->value,
            'subtotal' => $sellerOrder->subtotal,
            'discount_total' => $sellerOrder->discount_total,
            'tax_total' => $sellerOrder->tax_total,
            'shipping_total' => $sellerOrder->shipping_total,
            'commission_total' => $sellerOrder->commission_total,
            'seller_total' => $sellerOrder->seller_total,
            'fulfilled_at' => $sellerOrder->fulfilled_at,
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $sellerOrder->seller?->id,
                'name' => $sellerOrder->seller?->name,
                'slug' => $sellerOrder->seller?->slug,
            ]),
            'items' => SellerOrderItemResource::collection($this->whenLoaded('items')),
            'commission' => $this->whenLoaded('commission', fn () => $sellerOrder->commission !== null
                ? new SellerCommissionResource($sellerOrder->commission)
                : null),
            'created_at' => $sellerOrder->created_at,
            'updated_at' => $sellerOrder->updated_at,
        ];
    }
}
