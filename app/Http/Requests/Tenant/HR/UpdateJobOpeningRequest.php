<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentType;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Enums\Tenant\HR\JobRemoteType;
use App\Http\Requests\BaseRequest;
use App\Models\HR\JobOpening;
use Illuminate\Validation\Rule;

class UpdateJobOpeningRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var JobOpening $opening */
        $opening = $this->route('job_opening');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('job_openings', 'slug')->ignore($opening->id)],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('job_openings', 'code')->ignore($opening->id)],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['sometimes', 'nullable', 'integer', 'exists:designations,id'],
            'work_location_id' => ['sometimes', 'nullable', 'integer', 'exists:work_locations,id'],
            'employment_type' => ['sometimes', 'nullable', 'string', Rule::enum(EmploymentType::class)],
            'work_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remote_type' => ['sometimes', 'nullable', 'string', Rule::enum(JobRemoteType::class)],
            'experience_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::enum(JobOpeningStatus::class)],
            'openings_count' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_currency' => ['sometimes', 'string', 'size:3'],
            'description' => ['sometimes', 'nullable', 'string'],
            'short_description' => ['sometimes', 'nullable', 'string'],
            'requirements' => ['sometimes', 'nullable', 'string'],
            'responsibilities' => ['sometimes', 'nullable', 'string'],
            'qualifications' => ['sometimes', 'nullable', 'string'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'benefits' => ['sometimes', 'nullable', 'string'],
            'closes_at' => ['sometimes', 'nullable', 'date'],
            'seo' => ['sometimes', 'array'],
            'seo.meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo.meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo.canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
