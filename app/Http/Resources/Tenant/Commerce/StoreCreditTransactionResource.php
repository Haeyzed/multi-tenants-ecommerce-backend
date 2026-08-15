<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\StoreCreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StoreCreditTransaction
 */
class StoreCreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StoreCreditTransaction $transaction */
        $transaction = $this->resource;

        return [
            'id' => $transaction->id,
            'store_credit_account_id' => $transaction->store_credit_account_id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'reference_type' => $transaction->reference_type,
            'reference_id' => $transaction->reference_id,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at,
        ];
    }
}
