<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required_without:type', 'integer', Rule::exists('leave_types', 'id')],
            'type' => ['required_without:leave_type_id', 'string', 'max:50', Rule::exists('leave_types', 'code')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
