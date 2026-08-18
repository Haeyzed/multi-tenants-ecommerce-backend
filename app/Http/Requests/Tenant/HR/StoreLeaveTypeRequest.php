<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('leave_types', 'code')],
            'is_paid' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'default_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'allow_carry_over' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
