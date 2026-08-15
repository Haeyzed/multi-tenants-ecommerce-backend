<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Models\Tenant\ProductAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for product attributes.
 *
 * @mixin ProductAttribute
 */
class ProductAttributeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductAttribute $attribute */
        $attribute = $this->resource;

        return [
            'id' => $attribute->id,
            'name' => $attribute->name,
            'slug' => $attribute->slug,
            'sort_order' => $attribute->sort_order,
            'values' => $this->whenLoaded('values', fn () => $attribute->values->map(fn ($value) => [
                'id' => $value->id,
                'product_attribute_id' => $value->product_attribute_id,
                'value' => $value->value,
                'sort_order' => $value->sort_order,
            ])->values()),
            'created_at' => $attribute->created_at,
            'updated_at' => $attribute->updated_at,
        ];
    }
}
