<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Loyalty;

use App\Enums\Tenant\Loyalty\LoyaltyTransactionType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates loyalty ledger index filters.
 */
class IndexLoyaltyTransactionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', 'string', Rule::enum(LoyaltyTransactionType::class)],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
