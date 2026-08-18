<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\InterviewRecommendation;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreInterviewFeedbackRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'strengths' => ['sometimes', 'nullable', 'string'],
            'weaknesses' => ['sometimes', 'nullable', 'string'],
            'recommendation' => ['sometimes', 'nullable', 'string', Rule::enum(InterviewRecommendation::class)],
            'comments' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
