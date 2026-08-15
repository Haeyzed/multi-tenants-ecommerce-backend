<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Loyalty;

use App\Models\Tenant\LoyaltyTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyTransaction
 */
class LoyaltyTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LoyaltyTransaction $transaction */
        $transaction = $this->resource;

        return [
            'id' => $transaction->id,
            'loyalty_account_id' => $transaction->loyalty_account_id,
            'type' => $transaction->type,
            'points' => $transaction->points,
            'balance_after' => $transaction->balance_after,
            'reference_type' => $transaction->reference_type,
            'reference_id' => $transaction->reference_id,
            'description' => $transaction->description,
            'meta' => $transaction->meta,
            'created_at' => $transaction->created_at,
            'updated_at' => $transaction->updated_at,
        ];
    }
}
