<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateJobApplicationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cover_letter' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::enum(JobApplicationStatus::class)],
            'recruitment_stage_id' => ['sometimes', 'integer', 'exists:recruitment_stages,id'],
        ];
    }
}
