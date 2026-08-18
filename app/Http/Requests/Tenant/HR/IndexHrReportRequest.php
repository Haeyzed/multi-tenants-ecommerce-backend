<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

class IndexHrReportRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'as_of' => ['sometimes', 'nullable', 'date'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'format' => ['sometimes', 'nullable', 'string', 'in:json,csv'],
        ];
    }

    public function wantsCsv(): bool
    {
        return ($this->validated()['format'] ?? 'json') === 'csv';
    }
}
