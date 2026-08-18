<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Enums\Tenant\HR\PayFrequency;
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
        ];
    }
}
