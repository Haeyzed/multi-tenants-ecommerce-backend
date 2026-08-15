<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Catalog;

use App\Http\Resources\Tenant\Product\ProductResource;
use App\Http\Resources\Tenant\Product\ProductVariantResource;
use App\Models\Tenant\ProductBundleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for bundle line items.
 *
 * @mixin ProductBundleItem
 */
class ProductBundleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductBundleItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'bundle_product_id' => $item->bundle_product_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'sort_order' => $item->sort_order,
            'product' => $this->whenLoaded('product', fn () => new ProductResource($item->product)),
            'variant' => $this->whenLoaded('variant', fn () => $item->variant
                ? new ProductVariantResource($item->variant)
                : null),
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
