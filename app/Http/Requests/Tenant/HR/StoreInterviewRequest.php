<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\InterviewType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreInterviewRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_application_id' => ['required', 'integer', 'exists:job_applications,id'],
            'interview_type' => ['sometimes', 'string', Rule::enum(InterviewType::class)],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:480'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'status' => ['sometimes', 'string', Rule::enum(InterviewStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string'],
            'interviewer_ids' => ['sometimes', 'array'],
            'interviewer_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
