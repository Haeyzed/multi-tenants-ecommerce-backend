<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\ApplicationSource;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreJobApplicationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_opening_id' => ['required', 'integer', 'exists:job_openings,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'source' => ['sometimes', 'nullable', 'string', Rule::enum(ApplicationSource::class)],
            'status' => ['sometimes', 'string', Rule::enum(JobApplicationStatus::class)],
            'cover_letter' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }
}
