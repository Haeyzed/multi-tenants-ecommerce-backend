<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Loyalty;

use App\Enums\Tenant\Loyalty\LoyaltyAccountStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates staff loyalty account index filters.
 */
class IndexLoyaltyAccountRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(LoyaltyAccountStatus::class)],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
