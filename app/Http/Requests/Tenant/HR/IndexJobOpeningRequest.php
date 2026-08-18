<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class IndexJobOpeningRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(JobOpeningStatus::class)],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'sort' => ['sometimes', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
