<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

/**
 * Mark payroll run as paid.
 */
class PayPayrollRunRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'post_to_accounting' => ['sometimes', 'boolean'],
            'expense_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'payable_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'tax_payable_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'deduction_payable_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
        ];
    }
}
