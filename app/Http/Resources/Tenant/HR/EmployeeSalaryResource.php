<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\EmployeeSalary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeSalary
 */
class EmployeeSalaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EmployeeSalary $salary */
        $salary = $this->resource;

        return [
            'id' => $salary->id,
            'employee_id' => $salary->employee_id,
            'base_salary' => $salary->base_salary,
            'currency' => $salary->currency,
            'pay_frequency' => $salary->pay_frequency,
            'effective_from' => $salary->effective_from?->toDateString(),
            'created_at' => $salary->created_at,
            'updated_at' => $salary->updated_at,
        ];
    }
}
