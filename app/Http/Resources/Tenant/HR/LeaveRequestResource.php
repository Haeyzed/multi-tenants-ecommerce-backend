<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\HR\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LeaveRequest $leaveRequest */
        $leaveRequest = $this->resource;

        return [
            'id' => $leaveRequest->id,
            'employee_id' => $leaveRequest->employee_id,
            'leave_type_id' => $leaveRequest->leave_type_id,
            'type' => $leaveRequest->leaveType?->code,
            'start_date' => $leaveRequest->start_date?->toDateString(),
            'end_date' => $leaveRequest->end_date?->toDateString(),
            'status' => $leaveRequest->status,
            'reason' => $leaveRequest->reason,
            'reviewer_id' => $leaveRequest->reviewer_id,
            'reviewed_at' => $leaveRequest->reviewed_at,
            'review_notes' => $leaveRequest->review_notes,
            'employee' => $this->whenLoaded('employee', fn () => $leaveRequest->employee === null ? null : [
                'id' => $leaveRequest->employee->id,
                'employee_number' => $leaveRequest->employee->employee_number,
                'user' => $leaveRequest->employee->relationLoaded('user') && $leaveRequest->employee->user !== null ? [
                    'id' => $leaveRequest->employee->user->id,
                    'first_name' => $leaveRequest->employee->user->first_name,
                    'last_name' => $leaveRequest->employee->user->last_name,
                    'email' => $leaveRequest->employee->user->email,
                ] : null,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $leaveRequest->reviewer === null ? null : [
                'id' => $leaveRequest->reviewer->id,
                'first_name' => $leaveRequest->reviewer->first_name,
                'last_name' => $leaveRequest->reviewer->last_name,
                'email' => $leaveRequest->reviewer->email,
            ]),
            'leave_type' => $this->whenLoaded('leaveType', fn () => $leaveRequest->leaveType === null ? null : [
                'id' => $leaveRequest->leaveType->id,
                'name' => $leaveRequest->leaveType->name,
                'code' => $leaveRequest->leaveType->code,
            ]),
            'created_at' => $leaveRequest->created_at,
            'updated_at' => $leaveRequest->updated_at,
            'deleted_at' => $leaveRequest->deleted_at,
        ];
    }
}
