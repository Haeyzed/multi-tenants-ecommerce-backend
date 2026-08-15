<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Commerce;

use App\Models\Tenant\StoreCreditAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StoreCreditAccount
 */
class StoreCreditAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StoreCreditAccount $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'customer_id' => $account->customer_id,
            'balance' => $account->balance,
            'currency' => $account->currency,
            'status' => $account->status,
            'transactions' => StoreCreditTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
        ];
    }
}
