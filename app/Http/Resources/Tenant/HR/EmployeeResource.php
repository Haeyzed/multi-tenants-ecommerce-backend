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
            'job_title' => $employee->job_title,
            'employee_number' => $employee->employee_number,
            'employment_status' => $employee->employment_status,
            'hired_at' => $employee->hired_at?->toDateString(),
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
            'created_at' => $employee->created_at,
            'updated_at' => $employee->updated_at,
            'deleted_at' => $employee->deleted_at,
        ];
    }
}
