<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\Tenant\SellerPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerPayout
 */
class SellerPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SellerPayout $payout */
        $payout = $this->resource;

        return [
            'id' => $payout->id,
            'seller_id' => $payout->seller_id,
            'amount' => $payout->amount,
            'currency' => $payout->currency,
            'status' => $payout->status->value,
            'idempotency_key' => $payout->idempotency_key,
            'reference' => $payout->reference,
            'paid_at' => $payout->paid_at,
            'metadata' => $payout->metadata,
            'commissions' => SellerCommissionResource::collection($this->whenLoaded('commissions')),
            'created_at' => $payout->created_at,
            'updated_at' => $payout->updated_at,
        ];
    }
}
