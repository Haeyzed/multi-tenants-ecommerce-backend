<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\HR\EmployeeSalary;
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
            'components' => $this->whenLoaded('components', fn () => $salary->components->map(fn ($component) => [
                'id' => $component->id,
                'type' => $component->type,
                'calculation' => $component->calculation,
                'code' => $component->code,
                'label' => $component->label,
                'amount' => $component->amount,
                'is_tax' => $component->is_tax,
                'sort_order' => $component->sort_order,
            ])->values()),
            'created_at' => $salary->created_at,
            'updated_at' => $salary->updated_at,
        ];
    }
}
