<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\Tenant\SellerCommission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerCommission
 */
class SellerCommissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SellerCommission $commission */
        $commission = $this->resource;

        return [
            'id' => $commission->id,
            'seller_order_id' => $commission->seller_order_id,
            'seller_id' => $commission->seller_id,
            'order_id' => $commission->order_id,
            'commission_type' => $commission->commission_type->value,
            'commission_rate' => $commission->commission_rate,
            'commission_fixed_amount' => $commission->commission_fixed_amount,
            'order_subtotal' => $commission->order_subtotal,
            'commission_amount' => $commission->commission_amount,
            'seller_amount' => $commission->seller_amount,
            'status' => $commission->status->value,
            'earned_at' => $commission->earned_at,
            'created_at' => $commission->created_at,
            'updated_at' => $commission->updated_at,
        ];
    }
}
