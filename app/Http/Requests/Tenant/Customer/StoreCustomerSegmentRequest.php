<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates customer segment creation payloads.
 */
class StoreCustomerSegmentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('customer_segments', 'name')],
            'description' => ['sometimes', 'nullable', 'string'],
            'match' => ['sometimes', 'string', Rule::in(['all', 'any'])],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.type' => ['required', 'string', Rule::enum(CustomerSegmentRule::class)],
            'conditions.*.value' => ['sometimes', 'nullable'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
