<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\HR\InterviewFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Internal interviewer scorecard. Never used on public APIs.
 *
 * @mixin InterviewFeedback
 */
class InterviewFeedbackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InterviewFeedback $feedback */
        $feedback = $this->resource;

        return [
            'id' => $feedback->id,
            'interview_id' => $feedback->interview_id,
            'user_id' => $feedback->user_id,
            'rating' => $feedback->rating,
            'strengths' => $feedback->strengths,
            'weaknesses' => $feedback->weaknesses,
            'recommendation' => $feedback->recommendation,
            'comments' => $feedback->comments,
            'created_at' => $feedback->created_at,
            'updated_at' => $feedback->updated_at,
        ];
    }
}
