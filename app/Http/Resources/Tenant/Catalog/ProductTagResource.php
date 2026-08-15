<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Models\Tenant\ProductTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for product tags.
 *
 * @mixin ProductTag
 */
class ProductTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductTag $tag */
        $tag = $this->resource;

        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'is_active' => (bool) $tag->is_active,
            'products_count' => $this->when(isset($tag->products_count), $tag->products_count),
            'created_at' => $tag->created_at,
            'updated_at' => $tag->updated_at,
        ];
    }
}
