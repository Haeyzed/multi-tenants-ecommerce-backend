<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\GiftCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a gift card. The plain code is never exposed here.
 *
 * @mixin GiftCard
 */
class GiftCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GiftCard $giftCard */
        $giftCard = $this->resource;

        return [
            'id' => $giftCard->id,
            'masked_code' => '****-****-****-'.$giftCard->last_four,
            'last_four' => $giftCard->last_four,
            'initial_amount' => $giftCard->initial_amount,
            'balance' => $giftCard->balance,
            'currency' => $giftCard->currency,
            'status' => $giftCard->status,
            'expires_at' => $giftCard->expires_at,
            'activated_at' => $giftCard->activated_at,
            'customer_id' => $giftCard->customer_id,
            'purchased_order_id' => $giftCard->purchased_order_id,
            'meta' => $giftCard->meta,
            'transactions' => GiftCardTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $giftCard->created_at,
            'updated_at' => $giftCard->updated_at,
            'deleted_at' => $giftCard->deleted_at,
        ];
    }
}
