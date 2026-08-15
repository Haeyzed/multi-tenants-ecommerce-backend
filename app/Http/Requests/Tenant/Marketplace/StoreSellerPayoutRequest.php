<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Marketplace;

use App\Http\Requests\BaseRequest;

/**
 * Validates creating a seller payout batch.
 */
class StoreSellerPayoutRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'commission_ids' => ['required', 'array', 'min:1'],
            'commission_ids.*' => ['integer', 'exists:seller_commissions,id'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ];
    }
}
