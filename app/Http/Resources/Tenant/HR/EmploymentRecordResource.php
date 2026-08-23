<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\HR\EmploymentRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmploymentRecord
 */
class EmploymentRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EmploymentRecord $record */
        $record = $this->resource;

        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'change_type' => $record->change_type,
            'department_id' => $record->department_id,
            'designation_id' => $record->designation_id,
            'manager_id' => $record->manager_id,
            'job_title' => $record->job_title,
            'employment_status' => $record->employment_status,
            'employment_type' => $record->employment_type,
            'work_location' => $record->work_location,
            'effective_on' => $record->effective_on?->toDateString(),
            'notes' => $record->notes,
            'department' => $this->whenLoaded('department', fn () => $record->department === null ? null : [
                'id' => $record->department->id,
                'name' => $record->department->name,
            ]),
            'designation' => $this->whenLoaded('designation', fn () => $record->designation === null ? null : [
                'id' => $record->designation->id,
                'name' => $record->designation->name,
            ]),
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ];
    }
}
