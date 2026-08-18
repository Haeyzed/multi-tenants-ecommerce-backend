<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use App\Models\Tenant\LeaveType;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var LeaveType $leaveType */
        $leaveType = $this->route('leave_type');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'alpha_dash', Rule::unique('leave_types', 'code')->ignore($leaveType->id)],
            'is_paid' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'default_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'allow_carry_over' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
