<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates customer group update payloads.
 */
class UpdateCustomerGroupRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $groupId */
        $groupId = $this->route('customer_group');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('customer_groups', 'name')->ignore($groupId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
