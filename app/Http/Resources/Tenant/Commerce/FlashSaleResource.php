<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\FlashSale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FlashSale
 */
class FlashSaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FlashSale $flashSale */
        $flashSale = $this->resource;

        return [
            'id' => $flashSale->id,
            'name' => $flashSale->name,
            'slug' => $flashSale->slug,
            'description' => $flashSale->description,
            'starts_at' => $flashSale->starts_at,
            'ends_at' => $flashSale->ends_at,
            'is_active' => $flashSale->is_active,
            'stack_with_coupons' => $flashSale->stack_with_coupons,
            'status' => $flashSale->status()->value,
            'items' => FlashSaleItemResource::collection($this->whenLoaded('items')),
            'created_at' => $flashSale->created_at,
            'updated_at' => $flashSale->updated_at,
        ];
    }
}
