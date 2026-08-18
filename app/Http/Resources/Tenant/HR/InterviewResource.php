<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Interview
 */
class InterviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Interview $interview */
        $interview = $this->resource;

        return [
            'id' => $interview->id,
            'job_application_id' => $interview->job_application_id,
            'interview_type' => $interview->interview_type,
            'scheduled_at' => $interview->scheduled_at,
            'scheduled_at_local' => $interview->scheduled_at === null
                ? null
                : $interview->scheduled_at->copy()->timezone($interview->timezone ?: (string) config('app.timezone'))->toDateTimeString(),
            'timezone' => $interview->timezone,
            'duration_minutes' => $interview->duration_minutes,
            'location' => $interview->location,
            'meeting_url' => $interview->meeting_url,
            'meeting' => $this->whenLoaded('currentMeeting', fn () => $interview->currentMeeting === null
                ? null
                : new InterviewMeetingResource($interview->currentMeeting)),
            'status' => $interview->status,
            'notes' => $interview->notes,
            'application' => $this->whenLoaded('application', fn () => $interview->application === null ? null : [
                'id' => $interview->application->id,
                'first_name' => $interview->application->first_name,
                'last_name' => $interview->application->last_name,
                'status' => $interview->application->status,
                'job_opening_id' => $interview->application->job_opening_id,
                'job_opening' => $interview->application->relationLoaded('jobOpening') && $interview->application->jobOpening !== null
                    ? [
                        'id' => $interview->application->jobOpening->id,
                        'title' => $interview->application->jobOpening->title,
                    ]
                    : null,
            ]),
            'interviewers' => $this->whenLoaded('interviewers', fn () => $interview->interviewers->map(fn ($user) => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ])->values()),
            'feedback' => InterviewFeedbackResource::collection($this->whenLoaded('feedback')),
            'created_at' => $interview->created_at,
            'updated_at' => $interview->updated_at,
        ];
    }
}
