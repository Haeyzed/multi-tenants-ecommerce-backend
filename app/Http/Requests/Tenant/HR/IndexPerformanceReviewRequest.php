<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\PerformanceReviewStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class IndexPerformanceReviewRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'performance_cycle_id' => ['sometimes', 'nullable', 'integer', 'exists:performance_cycles,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(PerformanceReviewStatus::class)],
            'sort' => ['sometimes', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
