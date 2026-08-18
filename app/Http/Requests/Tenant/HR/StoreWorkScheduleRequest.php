<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreWorkScheduleRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('work_schedules', 'code')],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'overtime_policy_id' => ['sometimes', 'nullable', 'integer', 'exists:overtime_policies,id'],
            'days' => ['required', 'array', 'min:1'],
            'days.*.weekday' => ['required', 'integer', 'min:1', 'max:7', 'distinct'],
            'days.*.start_time' => ['required', 'date_format:H:i'],
            'days.*.end_time' => ['required', 'date_format:H:i'],
            'days.*.break_minutes' => ['sometimes', 'integer', 'min:0', 'max:600'],
        ];
    }
}
