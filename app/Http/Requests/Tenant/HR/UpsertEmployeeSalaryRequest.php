<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Enums\Tenant\HR\PayrollLineType;
use App\Enums\Tenant\HR\SalaryComponentCalculation;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Upsert employee salary compensation.
 */
class UpsertEmployeeSalaryRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'base_salary' => ['required', 'numeric', 'gt:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'pay_frequency' => ['sometimes', Rule::enum(PayFrequency::class)],
            'effective_from' => ['sometimes', 'date'],
            'components' => ['sometimes', 'array'],
            'components.*.type' => ['required_with:components', Rule::enum(PayrollLineType::class)],
            'components.*.calculation' => ['sometimes', Rule::enum(SalaryComponentCalculation::class)],
            'components.*.code' => ['required_with:components', 'string', 'max:50', 'alpha_dash'],
            'components.*.label' => ['required_with:components', 'string', 'max:255'],
            'components.*.amount' => ['required_with:components', 'numeric', 'gte:0'],
            'components.*.is_tax' => ['sometimes', 'boolean'],
            'components.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ];
    }
}
