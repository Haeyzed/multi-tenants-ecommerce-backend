<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates customer group creation payloads.
 */
class StoreCustomerGroupRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('customer_groups', 'name')],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
