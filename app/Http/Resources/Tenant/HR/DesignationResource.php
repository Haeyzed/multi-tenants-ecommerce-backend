<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\Designation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Designation
 */
class DesignationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Designation $designation */
        $designation = $this->resource;

        return [
            'id' => $designation->id,
            'department_id' => $designation->department_id,
            'name' => $designation->name,
            'code' => $designation->code,
            'description' => $designation->description,
            'is_active' => $designation->is_active,
            'employees_count' => $this->when(isset($designation->employees_count), $designation->employees_count),
            'department' => $this->whenLoaded('department', fn () => $designation->department === null ? null : [
                'id' => $designation->department->id,
                'name' => $designation->department->name,
                'code' => $designation->department->code,
            ]),
            'created_at' => $designation->created_at,
            'updated_at' => $designation->updated_at,
            'deleted_at' => $designation->deleted_at,
        ];
    }
}
