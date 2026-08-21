<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Http\Resources\Tenant\Catalog\SeoMetaResource;
use App\Models\HR\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public careers listing. Omits applications, candidates, and internal notes.
 *
 * @mixin JobOpening
 */
class PublicJobOpeningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var JobOpening $opening */
        $opening = $this->resource;

        return [
            'title' => $opening->title,
            'slug' => $opening->slug,
            'short_description' => $opening->short_description,
            'description' => $opening->description,
            'employment_type' => $opening->employment_type,
            'work_location' => $opening->work_location,
            'remote_type' => $opening->remote_type,
            'experience_level' => $opening->experience_level,
            'openings_count' => $opening->openings_count,
            'salary_min' => $opening->salary_min,
            'salary_max' => $opening->salary_max,
            'salary_currency' => $opening->salary_currency,
            'requirements' => $opening->requirements,
            'responsibilities' => $opening->responsibilities,
            'qualifications' => $opening->qualifications,
            'skills' => $opening->skills,
            'benefits' => $opening->benefits,
            'closes_at' => $opening->closes_at?->toDateString(),
            'published_at' => $opening->published_at,
            'department' => $this->whenLoaded('department', fn () => $opening->department === null ? null : [
                'name' => $opening->department->name,
            ]),
            'designation' => $this->whenLoaded('designation', fn () => $opening->designation === null ? null : [
                'name' => $opening->designation->name,
            ]),
            'work_location_record' => $this->whenLoaded('workLocation', fn () => $opening->workLocation === null ? null : [
                'name' => $opening->workLocation->name,
            ]),
            'seo' => $this->whenLoaded('seo', fn () => $opening->seo === null ? null : new SeoMetaResource($opening->seo)),
        ];
    }
}
