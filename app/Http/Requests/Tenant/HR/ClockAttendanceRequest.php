<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

class ClockAttendanceRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'biometric_token' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
