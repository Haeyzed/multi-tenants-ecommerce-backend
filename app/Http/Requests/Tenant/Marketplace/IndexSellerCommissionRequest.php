<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Marketplace;

use App\Http\Requests\BaseRequest;

/**
 * Validates commission listing filters.
 */
class IndexSellerCommissionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seller_id' => ['sometimes', 'nullable', 'integer', 'exists:sellers,id'],
            'order_id' => ['sometimes', 'nullable', 'integer', 'exists:orders,id'],
            'status' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
