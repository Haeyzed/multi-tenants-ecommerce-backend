<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateRecruitmentStageRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:100', 'alpha_dash'],
            'kind' => ['sometimes', 'string', Rule::enum(JobApplicationStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_terminal' => ['sometimes', 'boolean'],
        ];
    }
}
