<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreOvertimePolicyRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('overtime_policies', 'code')],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'weekday_rate_percent' => ['sometimes', 'integer', 'min:100', 'max:400'],
            'weekend_rate_percent' => ['sometimes', 'integer', 'min:100', 'max:400'],
            'holiday_rate_percent' => ['sometimes', 'integer', 'min:100', 'max:400'],
            'daily_threshold_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'max_daily_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'round_to_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ];
    }
}
