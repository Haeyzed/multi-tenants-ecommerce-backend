<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\PerformanceReviewStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdatePerformanceReviewRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reviewer_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::enum(PerformanceReviewStatus::class)],
        ];
    }
}
