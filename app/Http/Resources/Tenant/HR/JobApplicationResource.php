<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobApplication
 */
class JobApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var JobApplication $application */
        $application = $this->resource;

        return [
            'id' => $application->id,
            'job_opening_id' => $application->job_opening_id,
            'candidate_id' => $application->candidate_id,
            'recruitment_stage_id' => $application->recruitment_stage_id,
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'source' => $application->source,
            'applied_at' => $application->applied_at,
            'status' => $application->status,
            'cover_letter' => $application->cover_letter,
            'notes' => $application->notes,
            'hired_employee_id' => $application->hired_employee_id,
            'candidate' => $this->whenLoaded('candidate', fn () => $application->candidate === null ? null : new CandidateResource($application->candidate)),
            'stage' => $this->whenLoaded('stage', fn () => $application->stage === null ? null : [
                'id' => $application->stage->id,
                'name' => $application->stage->name,
                'slug' => $application->stage->slug,
                'kind' => $application->stage->kind,
            ]),
            'job_opening' => $this->whenLoaded('jobOpening', fn () => $application->jobOpening === null ? null : [
                'id' => $application->jobOpening->id,
                'title' => $application->jobOpening->title,
                'status' => $application->jobOpening->status,
            ]),
            'stage_history' => ApplicationStageHistoryResource::collection($this->whenLoaded('stageHistory')),
            'created_at' => $application->created_at,
            'updated_at' => $application->updated_at,
        ];
    }
}
