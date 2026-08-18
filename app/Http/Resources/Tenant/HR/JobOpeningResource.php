<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Http\Resources\Tenant\Catalog\SeoMetaResource;
use App\Models\Tenant\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobOpening
 */
class JobOpeningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var JobOpening $opening */
        $opening = $this->resource;

        return [
            'id' => $opening->id,
            'title' => $opening->title,
            'slug' => $opening->slug,
            'code' => $opening->code,
            'department_id' => $opening->department_id,
            'designation_id' => $opening->designation_id,
            'employment_type' => $opening->employment_type,
            'work_location' => $opening->work_location,
            'remote_type' => $opening->remote_type,
            'experience_level' => $opening->experience_level,
            'status' => $opening->status,
            'openings_count' => $opening->openings_count,
            'salary_min' => $opening->salary_min,
            'salary_max' => $opening->salary_max,
            'salary_currency' => $opening->salary_currency,
            'description' => $opening->description,
            'short_description' => $opening->short_description,
            'requirements' => $opening->requirements,
            'responsibilities' => $opening->responsibilities,
            'qualifications' => $opening->qualifications,
            'skills' => $opening->skills,
            'benefits' => $opening->benefits,
            'closes_at' => $opening->closes_at?->toDateString(),
            'published_at' => $opening->published_at,
            'closed_at' => $opening->closed_at,
            'applications_count' => $opening->applications_count ?? null,
            'department' => $this->whenLoaded('department', fn () => $opening->department === null ? null : [
                'id' => $opening->department->id,
                'name' => $opening->department->name,
            ]),
            'designation' => $this->whenLoaded('designation', fn () => $opening->designation === null ? null : [
                'id' => $opening->designation->id,
                'name' => $opening->designation->name,
            ]),
            'seo' => $this->whenLoaded('seo', fn () => $opening->seo === null ? null : new SeoMetaResource($opening->seo)),
            'created_at' => $opening->created_at,
            'updated_at' => $opening->updated_at,
        ];
    }
}
