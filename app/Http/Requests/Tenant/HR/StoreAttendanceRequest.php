<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::enum(AttendanceStatus::class)],
            'checked_in_at' => ['sometimes', 'nullable', 'date'],
            'checked_out_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:checked_in_at'],
            'overtime_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
