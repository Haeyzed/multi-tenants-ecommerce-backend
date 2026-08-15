<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Product;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\Inventory\InventoryResource;
use App\Http\Resources\Tenant\Unit\UnitResource;
use App\Models\Tenant\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant product variants.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->resource;
        $image = $variant->getFirstMedia(MediaCollection::Images->value);

        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'unit_id' => $variant->unit_id,
            'is_active' => (bool) $variant->is_active,
            'weight' => $variant->weight,
            'length' => $variant->length,
            'width' => $variant->width,
            'height' => $variant->height,
            'sort_order' => $variant->sort_order,
            'unit' => $this->whenLoaded('unit', fn () => new UnitResource($variant->unit)),
            'option_values' => $this->whenLoaded('optionValues'),
            'prices' => ProductPriceResource::collection($this->whenLoaded('prices')),
            'inventory' => InventoryResource::collection($this->whenLoaded('inventories')),
            'image' => $image ? new MediaResource($image) : null,
            'media' => MediaResource::collection(
                $variant->relationLoaded('media')
                    ? $variant->getMedia(MediaCollection::Images->value)
                    : collect(),
            ),
            'created_at' => $variant->created_at,
            'updated_at' => $variant->updated_at,
        ];
    }
}
