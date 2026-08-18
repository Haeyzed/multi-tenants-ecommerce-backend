<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\InterviewStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class IndexInterviewRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(InterviewStatus::class)],
            'job_application_id' => ['sometimes', 'nullable', 'integer', 'exists:job_applications,id'],
            'job_opening_id' => ['sometimes', 'nullable', 'integer', 'exists:job_openings,id'],
            'interviewer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'mine' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
