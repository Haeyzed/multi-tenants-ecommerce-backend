<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Brand;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Media\MediaResource;
use App\Models\Tenant\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for tenant brands.
 *
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Brand $brand */
        $brand = $this->resource;
        $logo = $brand->getFirstMedia(MediaCollection::Logo->value);

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'description' => $brand->description,
            'is_active' => (bool) $brand->is_active,
            'sort_order' => $brand->sort_order,
            'logo' => $logo ? new MediaResource($logo) : null,
            'products_count' => $this->when(isset($brand->products_count), $brand->products_count),
            'created_at' => $brand->created_at,
            'updated_at' => $brand->updated_at,
        ];
    }
}
