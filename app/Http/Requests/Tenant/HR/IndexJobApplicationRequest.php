<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class IndexJobApplicationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(JobApplicationStatus::class)],
            'job_opening_id' => ['sometimes', 'nullable', 'integer', 'exists:job_openings,id'],
            'candidate_id' => ['sometimes', 'nullable', 'integer', 'exists:candidates,id'],
            'recruitment_stage_id' => ['sometimes', 'nullable', 'integer', 'exists:recruitment_stages,id'],
            'sort' => ['sometimes', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
