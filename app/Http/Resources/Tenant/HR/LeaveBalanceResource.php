<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveBalance
 */
class LeaveBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LeaveBalance $balance */
        $balance = $this->resource;

        return [
            'id' => $balance->id,
            'employee_id' => $balance->employee_id,
            'leave_type_id' => $balance->leave_type_id,
            'year' => $balance->year,
            'entitled' => $balance->entitled,
            'carried_over' => $balance->carried_over,
            'used' => $balance->used,
            'remaining' => $balance->remaining(),
            'leave_type' => $this->whenLoaded('leaveType', fn () => $balance->leaveType === null ? null : [
                'id' => $balance->leaveType->id,
                'name' => $balance->leaveType->name,
                'code' => $balance->leaveType->code,
                'is_paid' => $balance->leaveType->is_paid,
            ]),
        ];
    }
}
