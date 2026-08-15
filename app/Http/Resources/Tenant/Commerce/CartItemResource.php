<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a cart line item.
 *
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'cart_id' => $item->cart_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'subtotal' => $item->subtotal,
            'metadata' => $item->metadata,
            'product' => $this->whenLoaded('product', function () use ($item) {
                return [
                    'id' => $item->product?->id,
                    'name' => $item->product?->name,
                    'slug' => $item->product?->slug,
                    'prices' => $item->product?->relationLoaded('prices')
                        ? $item->product->prices->map(fn ($price) => [
                            'id' => $price->id,
                            'currency' => $price->currency,
                            'amount' => $price->amount,
                            'is_active' => (bool) $price->is_active,
                        ])->values()
                        : [],
                ];
            }),
            'variant' => $this->whenLoaded('productVariant', function () use ($item) {
                if ($item->productVariant === null) {
                    return null;
                }

                return [
                    'id' => $item->productVariant->id,
                    'name' => $item->productVariant->name,
                    'sku' => $item->productVariant->sku,
                    'prices' => $item->productVariant->relationLoaded('prices')
                        ? $item->productVariant->prices->map(fn ($price) => [
                            'id' => $price->id,
                            'currency' => $price->currency,
                            'amount' => $price->amount,
                            'is_active' => (bool) $price->is_active,
                        ])->values()
                        : [],
                ];
            }),
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
