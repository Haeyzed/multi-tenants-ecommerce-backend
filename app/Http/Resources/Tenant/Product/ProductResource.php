<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Product;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Brand\BrandResource;
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
            'tax_class' => $product->tax_class,
            'weight' => $product->weight,
            'length' => $product->length,
            'width' => $product->width,
            'height' => $product->height,
            'published_at' => $product->published_at,
            'sort_order' => $product->sort_order,
            'brand' => $this->whenLoaded('brand', fn () => new BrandResource($product->brand)),
            'unit' => $this->whenLoaded('unit', fn () => new UnitResource($product->unit)),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'attribute_values' => $this->whenLoaded('attributeValues'),
            'prices' => ProductPriceResource::collection($this->whenLoaded('prices')),
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
