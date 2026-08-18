<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreJobOpeningRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('job_openings', 'code')],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['sometimes', 'nullable', 'integer', 'exists:designations,id'],
            'status' => ['sometimes', 'string', Rule::enum(JobOpeningStatus::class)],
            'openings_count' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'description' => ['sometimes', 'nullable', 'string'],
            'closes_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
