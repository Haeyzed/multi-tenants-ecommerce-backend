<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a customer wishlist.
 *
 * @mixin Wishlist
 */
class WishlistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Wishlist $wishlist */
        $wishlist = $this->resource;

        return [
            'id' => $wishlist->id,
            'customer_id' => $wishlist->customer_id,
            'items' => WishlistItemResource::collection($this->whenLoaded('items')),
            'item_count' => $wishlist->relationLoaded('items') ? $wishlist->items->count() : null,
            'created_at' => $wishlist->created_at,
            'updated_at' => $wishlist->updated_at,
        ];
    }
}
