<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Marketplace;

use App\Http\Requests\BaseRequest;

/**
 * Validates payout listing filters.
 */
class IndexSellerPayoutRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seller_id' => ['sometimes', 'nullable', 'integer', 'exists:sellers,id'],
            'status' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
