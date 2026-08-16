<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Coupon
 */
class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->resource;

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'description' => $coupon->description,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'minimum_order_amount' => $coupon->minimum_order_amount,
            'maximum_discount' => $coupon->maximum_discount,
            'usage_limit' => $coupon->usage_limit,
            'usage_limit_per_customer' => $coupon->usage_limit_per_customer,
            'starts_at' => $coupon->starts_at,
            'expires_at' => $coupon->expires_at,
            'is_active' => $coupon->is_active,
            'customer_group_id' => $coupon->customer_group_id,
            'product_ids' => $this->whenLoaded('products', fn () => $coupon->products->pluck('id')->values()),
            'category_ids' => $this->whenLoaded('categories', fn () => $coupon->categories->pluck('id')->values()),
            'created_at' => $coupon->created_at,
            'updated_at' => $coupon->updated_at,
            'deleted_at' => $coupon->deleted_at,
        ];
    }
}
