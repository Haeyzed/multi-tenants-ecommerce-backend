<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a wishlist line item.
 *
 * @mixin WishlistItem
 */
class WishlistItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WishlistItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'wishlist_id' => $item->wishlist_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
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
            'variant' => $this->whenLoaded('variant', function () use ($item) {
                if ($item->variant === null) {
                    return null;
                }

                return [
                    'id' => $item->variant->id,
                    'name' => $item->variant->name,
                    'sku' => $item->variant->sku,
                    'prices' => $item->variant->relationLoaded('prices')
                        ? $item->variant->prices->map(fn ($price) => [
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
