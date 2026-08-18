<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class HireCandidateRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['sometimes', 'nullable', 'integer', 'exists:designations,id'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'nullable', 'string', Rule::enum(EmploymentType::class)],
            'work_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'work_location_id' => ['sometimes', 'nullable', 'integer', 'exists:work_locations,id'],
            'hired_at' => ['sometimes', 'nullable', 'date'],
            'base_salary' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'role' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
