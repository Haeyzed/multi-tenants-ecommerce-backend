<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(AttendanceStatus::class)],
            'checked_in_at' => ['sometimes', 'nullable', 'date'],
            'checked_out_at' => ['sometimes', 'nullable', 'date'],
            'overtime_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'overtime_rate_percent' => ['sometimes', 'integer', 'min:0', 'max:400'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
