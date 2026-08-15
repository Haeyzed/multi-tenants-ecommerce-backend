<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\GiftCardTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GiftCardTransaction
 */
class GiftCardTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GiftCardTransaction $transaction */
        $transaction = $this->resource;

        return [
            'id' => $transaction->id,
            'gift_card_id' => $transaction->gift_card_id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'order_id' => $transaction->order_id,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at,
        ];
    }
}
