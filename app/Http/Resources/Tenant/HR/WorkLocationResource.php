<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\HR\WorkLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkLocation
 */
class WorkLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkLocation $location */
        $location = $this->resource;

        return [
            'id' => $location->id,
            'name' => $location->name,
            'code' => $location->code,
            'address' => $location->address,
            'is_active' => $location->is_active,
            'employees_count' => $this->when(isset($location->employees_count), $location->employees_count),
            'job_openings_count' => $this->when(isset($location->job_openings_count), $location->job_openings_count),
            'created_at' => $location->created_at,
            'updated_at' => $location->updated_at,
            'deleted_at' => $location->deleted_at,
        ];
    }
}
