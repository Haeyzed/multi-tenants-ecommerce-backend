<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\EmploymentType;
use App\Http\Requests\BaseRequest;
use App\Models\Tenant\HR\Employee;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['sometimes', 'nullable', 'integer', 'exists:designations,id'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'work_schedule_id' => ['sometimes', 'nullable', 'integer', 'exists:work_schedules,id'],
            'work_location_id' => ['sometimes', 'nullable', 'integer', 'exists:work_locations,id'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employee_number' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('employees', 'employee_number')->ignore($employee->id)],
            'employment_status' => ['sometimes', 'string', Rule::enum(EmploymentStatus::class)],
            'employment_type' => ['sometimes', 'nullable', 'string', Rule::enum(EmploymentType::class)],
            'work_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hired_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:32'],
            'account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pension_pin' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nhf_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nsitf_number' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
