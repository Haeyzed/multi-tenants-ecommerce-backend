<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

/**
 * Create a draft payroll run.
 */
class StorePayrollRunRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required_with:period_end', 'nullable', 'date'],
            'period_end' => ['required_with:period_start', 'nullable', 'date', 'after_or_equal:period_start'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
