<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Storefront;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Brand\BrandResource;
use App\Http\Resources\Tenant\Catalog\ProductBadgeResource;
use App\Http\Resources\Tenant\Catalog\ProductBundleItemResource;
use App\Http\Resources\Tenant\Catalog\ProductSpecificationResource;
use App\Http\Resources\Tenant\Catalog\ProductTagResource;
use App\Http\Resources\Tenant\Catalog\SeoMetaResource;
use App\Http\Resources\Tenant\Category\CategoryResource;
use App\Http\Resources\Tenant\Product\ProductVariantResource;
use App\Http\Resources\Tenant\Unit\UnitResource;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe storefront product resource (no cost_amount).
 *
 * @mixin Product
 */
class StorefrontProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'type' => $product->type,
            'has_variants' => (bool) $product->has_variants,
            'is_featured' => (bool) $product->is_featured,
            'allow_backorder' => (bool) $product->allow_backorder,
            'is_preorder' => (bool) $product->is_preorder,
            'preorder_start_at' => $product->preorder_start_at,
            'preorder_end_at' => $product->preorder_end_at,
            'minimum_purchase_quantity' => $product->minimum_purchase_quantity,
            'maximum_purchase_quantity' => $product->maximum_purchase_quantity,
            'average_rating' => $product->average_rating,
            'reviews_count' => $product->reviews_count,
            'weight' => $product->weight,
            'length' => $product->length,
            'width' => $product->width,
            'height' => $product->height,
            'availability' => $product->getAttribute('availability'),
            'brand' => $this->whenLoaded('brand', fn () => new BrandResource($product->brand)),
            'unit' => $this->whenLoaded('unit', fn () => new UnitResource($product->unit)),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => ProductTagResource::collection($this->whenLoaded('tags')),
            'badges' => ProductBadgeResource::collection($this->whenLoaded('badges')),
            'specifications' => ProductSpecificationResource::collection($this->whenLoaded('specifications')),
            'seo' => $this->whenLoaded('seo', fn () => $product->seo
                ? new SeoMetaResource($product->seo)
                : null),
            'related_products' => StorefrontProductResource::collection($this->whenLoaded('relatedProducts')),
            'upsells' => StorefrontProductResource::collection($this->whenLoaded('upsells')),
            'cross_sells' => StorefrontProductResource::collection($this->whenLoaded('crossSells')),
            'bundle_items' => ProductBundleItemResource::collection($this->whenLoaded('bundleItems')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'prices' => $this->whenLoaded('prices', fn () => $product->prices->map(fn (ProductPrice $price) => [
                'id' => $price->id,
                'currency' => $price->currency,
                'amount' => $price->amount,
                'compare_at_amount' => $price->compare_at_amount,
                'is_active' => (bool) $price->is_active,
                'starts_at' => $price->starts_at,
                'ends_at' => $price->ends_at,
            ])->values()),
            'media' => MediaResource::collection(
                $product->relationLoaded('media')
                    ? $product->getMedia(MediaCollection::Images->value)
                    : collect(),
            ),
        ];
    }
}
