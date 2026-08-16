<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\Tenant\Seller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Seller
 */
class SellerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Seller $seller */
        $seller = $this->resource;

        return [
            'id' => $seller->id,
            'name' => $seller->name,
            'slug' => $seller->slug,
            'description' => $seller->description,
            'email' => $seller->email,
            'phone' => $seller->phone,
            'status' => $seller->status,
            'verification_status' => $seller->verification_status,
            'commission_type' => $seller->commission_type,
            'commission_rate' => $seller->commission_rate,
            'commission_fixed_amount' => $seller->commission_fixed_amount,
            'seller_group_id' => $seller->seller_group_id,
            'seller_group' => new SellerGroupResource($this->whenLoaded('sellerGroup')),
            'logo_url' => $seller->logo_url,
            'offers_count' => $seller->offers_count ?? null,
            'can_sell' => $seller->canSell(),
            'created_at' => $seller->created_at,
            'updated_at' => $seller->updated_at,
        ];
    }
}
