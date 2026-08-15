<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Customer;

use App\Enums\Tenant\Customer\CustomerStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates customer status update payloads.
 */
class UpdateCustomerStatusRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(CustomerStatus::class)],
        ];
    }
}
