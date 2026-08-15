<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Refund $refund */
        $refund = $this->resource;

        return [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'order_payment_id' => $refund->order_payment_id,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'reference' => $refund->reference,
            'status' => $refund->status->value,
            'reason' => $refund->reason,
            'provider_refund_id' => $refund->provider_refund_id,
            'processed_at' => $refund->processed_at,
            'created_at' => $refund->created_at,
            'updated_at' => $refund->updated_at,
        ];
    }
}
