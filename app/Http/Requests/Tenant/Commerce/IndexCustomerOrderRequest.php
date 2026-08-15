<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Commerce;

use App\Enums\Tenant\Commerce\OrderStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates customer order index filters.
 */
class IndexCustomerOrderRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(OrderStatus::class)],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
