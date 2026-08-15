<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Product;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Brand\BrandResource;
use App\Http\Resources\Tenant\Catalog\ProductBadgeResource;
use App\Http\Resources\Tenant\Catalog\ProductBundleItemResource;
use App\Http\Resources\Tenant\Catalog\ProductCollectionResource;
use App\Http\Resources\Tenant\Catalog\ProductSpecificationResource;
use App\Http\Resources\Tenant\Catalog\ProductTagResource;
use App\Http\Resources\Tenant\Catalog\SeoMetaResource;
use App\Http\Resources\Tenant\Category\CategoryResource;
use App\Http\Resources\Tenant\Unit\UnitResource;
use App\Models\Tenant\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant products.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'brand_id' => $product->brand_id,
            'unit_id' => $product->unit_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'type' => $product->type,
            'status' => $product->status,
            'visibility' => $product->visibility,
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
            'tax_class' => $product->tax_class,
            'weight' => $product->weight,
            'length' => $product->length,
            'width' => $product->width,
            'height' => $product->height,
            'published_at' => $product->published_at,
            'unpublished_at' => $product->unpublished_at,
            'sort_order' => $product->sort_order,
            'availability' => $this->when(
                $product->getAttribute('availability') !== null,
                $product->getAttribute('availability'),
            ),
            'brand' => $this->whenLoaded('brand', fn () => new BrandResource($product->brand)),
            'unit' => $this->whenLoaded('unit', fn () => new UnitResource($product->unit)),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'attribute_values' => $this->whenLoaded('attributeValues'),
            'prices' => ProductPriceResource::collection($this->whenLoaded('prices')),
            'tags' => ProductTagResource::collection($this->whenLoaded('tags')),
            'badges' => ProductBadgeResource::collection($this->whenLoaded('badges')),
            'collections' => ProductCollectionResource::collection($this->whenLoaded('collections')),
            'specifications' => ProductSpecificationResource::collection($this->whenLoaded('specifications')),
            'seo' => $this->whenLoaded('seo', fn () => $product->seo
                ? new SeoMetaResource($product->seo)
                : null),
            'related_products' => ProductResource::collection($this->whenLoaded('relatedProducts')),
            'upsells' => ProductResource::collection($this->whenLoaded('upsells')),
            'cross_sells' => ProductResource::collection($this->whenLoaded('crossSells')),
            'bundle_items' => ProductBundleItemResource::collection($this->whenLoaded('bundleItems')),
            'media' => MediaResource::collection(
                $product->relationLoaded('media')
                    ? $product->getMedia(MediaCollection::Images->value)
                    : collect(),
            ),
            'variants_count' => $this->when(isset($product->variants_count), $product->variants_count),
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }
}
