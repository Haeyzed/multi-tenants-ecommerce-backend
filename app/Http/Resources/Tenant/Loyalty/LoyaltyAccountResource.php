<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Loyalty;

use App\Http\Resources\Tenant\Customer\CustomerResource;
use App\Models\Tenant\LoyaltyAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoyaltyAccount
 */
class LoyaltyAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LoyaltyAccount $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'customer_id' => $account->customer_id,
            'balance' => $account->balance,
            'lifetime_earned' => $account->lifetime_earned,
            'lifetime_redeemed' => $account->lifetime_redeemed,
            'status' => $account->status,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($account->customer)),
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
        ];
    }
}
