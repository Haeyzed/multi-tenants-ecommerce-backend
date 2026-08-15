<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Shipping;

use App\Models\Tenant\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingMethod
 */
class ShippingMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ShippingMethod $method */
        $method = $this->resource;

        return [
            'id' => $method->id,
            'name' => $method->name,
            'code' => $method->code,
            'description' => $method->description,
            'amount' => $method->amount,
            'min_order_amount' => $method->min_order_amount,
            'is_active' => (bool) $method->is_active,
            'sort_order' => $method->sort_order,
            'estimated_days_min' => $method->estimated_days_min,
            'estimated_days_max' => $method->estimated_days_max,
            'created_at' => $method->created_at,
            'updated_at' => $method->updated_at,
        ];
    }
}
