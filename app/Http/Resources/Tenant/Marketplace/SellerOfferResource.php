<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\Tenant\SellerOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerOffer
 */
class SellerOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SellerOffer $offer */
        $offer = $this->resource;

        return [
            'id' => $offer->id,
            'seller_id' => $offer->seller_id,
            'product_id' => $offer->product_id,
            'product_variant_id' => $offer->product_variant_id,
            'sku' => $offer->sku,
            'currency' => $offer->currency,
            'price' => $offer->price,
            'compare_at_price' => $offer->compare_at_price,
            'cost' => $offer->cost,
            'status' => $offer->status,
            'metadata' => $offer->metadata,
            'seller' => $this->whenLoaded('seller', fn () => new SellerResource($offer->seller)),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $offer->product?->id,
                'name' => $offer->product?->name,
                'slug' => $offer->product?->slug,
            ]),
            'product_variant' => $this->whenLoaded('productVariant', fn () => $offer->productVariant ? [
                'id' => $offer->productVariant->id,
                'sku' => $offer->productVariant->sku,
            ] : null),
            'created_at' => $offer->created_at,
            'updated_at' => $offer->updated_at,
        ];
    }
}
