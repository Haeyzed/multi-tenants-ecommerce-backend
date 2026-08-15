<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Http\Resources\Tenant\Product\ProductVariantResource;
use App\Models\Tenant\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for product reviews.
 *
 * @mixin ProductReview
 */
class ProductReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductReview $review */
        $review = $this->resource;

        return [
            'id' => $review->id,
            'customer_id' => $review->customer_id,
            'product_id' => $review->product_id,
            'product_variant_id' => $review->product_variant_id,
            'rating' => $review->rating,
            'title' => $review->title,
            'content' => $review->content,
            'status' => $review->status,
            'verified_purchase' => (bool) $review->verified_purchase,
            'approved_at' => $review->approved_at,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $review->customer?->id,
                'name' => $review->customer?->full_name,
            ]),
            'product' => $this->whenLoaded('product', fn () => new ProductResource($review->product)),
            'variant' => $this->whenLoaded('variant', fn () => $review->variant
                ? new ProductVariantResource($review->variant)
                : null),
            'images' => MediaResource::collection($this->whenLoaded('media')),
            'created_at' => $review->created_at,
            'updated_at' => $review->updated_at,
        ];
    }
}
