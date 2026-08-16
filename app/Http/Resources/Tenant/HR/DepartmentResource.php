<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Department $department */
        $department = $this->resource;

        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description,
            'is_active' => $department->is_active,
            'employees_count' => $this->when(isset($department->employees_count), $department->employees_count),
            'created_at' => $department->created_at,
            'updated_at' => $department->updated_at,
            'deleted_at' => $department->deleted_at,
        ];
    }
}
