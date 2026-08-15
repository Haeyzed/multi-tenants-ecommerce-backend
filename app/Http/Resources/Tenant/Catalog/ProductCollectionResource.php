<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Http\Resources\Tenant\Storefront\StorefrontProductResource;
use App\Models\Tenant\ProductCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for product collections.
 *
 * @mixin ProductCollection
 */
class ProductCollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductCollection $collection */
        $collection = $this->resource;
        $image = $collection->getFirstMedia(MediaCollection::Image->value);

        $products = $this->whenLoaded('products', function () use ($request, $collection) {
            if ($request->routeIs('storefront.*')) {
                return StorefrontProductResource::collection($collection->products);
            }

            return ProductResource::collection($collection->products);
        });

        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'description' => $collection->description,
            'type' => $collection->type,
            'status' => $collection->status,
            'sort_order' => $collection->sort_order,
            'published_at' => $collection->published_at,
            'starts_at' => $collection->starts_at,
            'ends_at' => $collection->ends_at,
            'image' => $image ? new MediaResource($image) : null,
            'products' => $products,
            'seo' => $this->whenLoaded('seo', fn () => $collection->seo
                ? new SeoMetaResource($collection->seo)
                : null),
            'products_count' => $this->when(isset($collection->products_count), $collection->products_count),
            'created_at' => $collection->created_at,
            'updated_at' => $collection->updated_at,
        ];
    }
}
