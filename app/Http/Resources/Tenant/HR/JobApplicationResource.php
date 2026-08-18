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
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'status' => $application->status,
            'cover_letter' => $application->cover_letter,
            'notes' => $application->notes,
            'hired_employee_id' => $application->hired_employee_id,
            'job_opening' => $this->whenLoaded('jobOpening', fn () => $application->jobOpening === null ? null : [
                'id' => $application->jobOpening->id,
                'title' => $application->jobOpening->title,
                'status' => $application->jobOpening->status,
            ]),
            'created_at' => $application->created_at,
            'updated_at' => $application->updated_at,
        ];
    }
}
