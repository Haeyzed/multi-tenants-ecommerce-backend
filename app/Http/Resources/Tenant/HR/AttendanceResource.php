<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Attendance $attendance */
        $attendance = $this->resource;

        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'work_date' => $attendance->work_date?->toDateString(),
            'status' => $attendance->status,
            'checked_in_at' => $attendance->checked_in_at,
            'checked_out_at' => $attendance->checked_out_at,
            'notes' => $attendance->notes,
            'employee' => $this->whenLoaded('employee', fn () => $attendance->employee === null ? null : [
                'id' => $attendance->employee->id,
                'employee_number' => $attendance->employee->employee_number,
                'user' => $attendance->employee->relationLoaded('user') && $attendance->employee->user !== null ? [
                    'id' => $attendance->employee->user->id,
                    'first_name' => $attendance->employee->user->first_name,
                    'last_name' => $attendance->employee->user->last_name,
                    'email' => $attendance->employee->user->email,
                ] : null,
            ]),
            'created_at' => $attendance->created_at,
            'updated_at' => $attendance->updated_at,
        ];
    }
}
