<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

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
            'code' => $opening->code,
            'department_id' => $opening->department_id,
            'designation_id' => $opening->designation_id,
            'status' => $opening->status,
            'openings_count' => $opening->openings_count,
            'description' => $opening->description,
            'closes_at' => $opening->closes_at?->toDateString(),
            'applications_count' => $opening->applications_count ?? null,
            'department' => $this->whenLoaded('department', fn () => $opening->department === null ? null : [
                'id' => $opening->department->id,
                'name' => $opening->department->name,
            ]),
            'created_at' => $opening->created_at,
            'updated_at' => $opening->updated_at,
        ];
    }
}
