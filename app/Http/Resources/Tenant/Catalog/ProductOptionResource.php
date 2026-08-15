<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Models\Tenant\ProductOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for product options.
 *
 * @mixin ProductOption
 */
class ProductOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductOption $option */
        $option = $this->resource;

        return [
            'id' => $option->id,
            'name' => $option->name,
            'slug' => $option->slug,
            'sort_order' => $option->sort_order,
            'values' => $this->whenLoaded('values', fn () => $option->values->map(fn ($value) => [
                'id' => $value->id,
                'product_option_id' => $value->product_option_id,
                'value' => $value->value,
                'slug' => $value->slug,
                'sort_order' => $value->sort_order,
            ])->values()),
            'created_at' => $option->created_at,
            'updated_at' => $option->updated_at,
        ];
    }
}
