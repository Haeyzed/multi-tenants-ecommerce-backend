<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\EmployeeSalaryRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeSalaryRevision
 */
class EmployeeSalaryRevisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EmployeeSalaryRevision $revision */
        $revision = $this->resource;

        return [
            'id' => $revision->id,
            'employee_id' => $revision->employee_id,
            'base_salary' => $revision->base_salary,
            'currency' => $revision->currency,
            'pay_frequency' => $revision->pay_frequency,
            'effective_from' => $revision->effective_from?->toDateString(),
            'effective_to' => $revision->effective_to?->toDateString(),
            'components' => $revision->components,
            'created_at' => $revision->created_at,
            'updated_at' => $revision->updated_at,
        ];
    }
}
