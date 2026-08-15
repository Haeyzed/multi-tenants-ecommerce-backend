<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Promotion $promotion */
        $promotion = $this->resource;

        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'slug' => $promotion->slug,
            'description' => $promotion->description,
            'type' => $promotion->type,
            'value' => $promotion->value,
            'min_order_amount' => $promotion->min_order_amount,
            'max_discount' => $promotion->max_discount,
            'priority' => $promotion->priority,
            'is_exclusive' => $promotion->is_exclusive,
            'is_stackable' => $promotion->is_stackable,
            'is_active' => $promotion->is_active,
            'starts_at' => $promotion->starts_at,
            'ends_at' => $promotion->ends_at,
            'metadata' => $promotion->metadata,
            'product_ids' => $this->whenLoaded('products', fn () => $promotion->products->pluck('id')->values()),
            'category_ids' => $this->whenLoaded('categories', fn () => $promotion->categories->pluck('id')->values()),
            'created_at' => $promotion->created_at,
            'updated_at' => $promotion->updated_at,
            'deleted_at' => $promotion->deleted_at,
        ];
    }
}
