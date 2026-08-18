<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;

        return [
            'id' => $employee->id,
            'user_id' => $employee->user_id,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'manager_id' => $employee->manager_id,
            'job_title' => $employee->job_title,
            'employee_number' => $employee->employee_number,
            'employment_status' => $employee->employment_status,
            'employment_type' => $employee->employment_type,
            'work_location' => $employee->work_location,
            'hired_at' => $employee->hired_at?->toDateString(),
            'terminated_at' => $employee->terminated_at?->toDateString(),
            'notes' => $employee->notes,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $employee->user->id,
                'first_name' => $employee->user->first_name,
                'last_name' => $employee->user->last_name,
                'email' => $employee->user->email,
                'avatar_url' => $employee->user->avatar_url,
            ]),
            'department' => $this->whenLoaded('department', fn () => $employee->department === null ? null : [
                'id' => $employee->department->id,
                'name' => $employee->department->name,
                'code' => $employee->department->code,
            ]),
            'designation' => $this->whenLoaded('designation', fn () => $employee->designation === null ? null : [
                'id' => $employee->designation->id,
                'name' => $employee->designation->name,
                'code' => $employee->designation->code,
            ]),
            'manager' => $this->whenLoaded('manager', fn () => $employee->manager === null ? null : [
                'id' => $employee->manager->id,
                'employee_number' => $employee->manager->employee_number,
                'user' => $employee->manager->relationLoaded('user') && $employee->manager->user !== null ? [
                    'id' => $employee->manager->user->id,
                    'first_name' => $employee->manager->user->first_name,
                    'last_name' => $employee->manager->user->last_name,
                ] : null,
            ]),
            'created_at' => $employee->created_at,
            'updated_at' => $employee->updated_at,
            'deleted_at' => $employee->deleted_at,
        ];
    }
}
