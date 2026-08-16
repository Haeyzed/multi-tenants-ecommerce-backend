<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\Customer\CustomerStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates admin customer update payloads.
 */
class UpdateCustomerRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var int|string|null $customerId */
        $customerId = $this->route('customer');

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customerId)->withoutTrashed(),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')->ignore($customerId)->withoutTrashed(),
            ],
            'status' => ['sometimes', 'required', 'string', Rule::enum(CustomerStatus::class)],
        ];
    }
}
