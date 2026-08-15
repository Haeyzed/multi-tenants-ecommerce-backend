<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Product;

use App\Models\Tenant\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for product and variant prices.
 *
 * @mixin ProductPrice
 */
class ProductPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductPrice $price */
        $price = $this->resource;

        return [
            'id' => $price->id,
            'currency' => $price->currency,
            'amount' => $price->amount,
            'compare_at_amount' => $price->compare_at_amount,
            'cost_amount' => $price->cost_amount,
            'is_active' => (bool) $price->is_active,
            'starts_at' => $price->starts_at,
            'ends_at' => $price->ends_at,
            'created_at' => $price->created_at,
            'updated_at' => $price->updated_at,
        ];
    }
}
