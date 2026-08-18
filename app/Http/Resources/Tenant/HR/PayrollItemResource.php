<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\PayrollItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollItem
 */
class PayrollItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PayrollItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'payroll_run_id' => $item->payroll_run_id,
            'employee_id' => $item->employee_id,
            'base_salary' => $item->base_salary,
            'gross_pay' => $item->gross_pay,
            'deduction_total' => $item->deduction_total,
            'net_pay' => $item->net_pay,
            'working_days' => $item->working_days,
            'scheduled_days' => $item->scheduled_days,
            'absent_days' => $item->absent_days,
            'unpaid_leave_days' => $item->unpaid_leave_days,
            'overtime_minutes' => $item->overtime_minutes,
            'ytd_gross' => $item->ytd_gross,
            'ytd_paye' => $item->ytd_paye,
            'employer_pension' => $item->employer_pension,
            'employer_nsitf' => $item->employer_nsitf,
            'bank_name' => $item->bank_name,
            'bank_code' => $item->bank_code,
            'account_number' => $item->account_number,
            'account_name' => $item->account_name,
            'notes' => $item->notes,
            'employee' => $this->whenLoaded('employee', fn () => $item->employee === null ? null : [
                'id' => $item->employee->id,
                'employee_number' => $item->employee->employee_number,
                'user' => $item->employee->relationLoaded('user') && $item->employee->user !== null ? [
                    'id' => $item->employee->user->id,
                    'first_name' => $item->employee->user->first_name,
                    'last_name' => $item->employee->user->last_name,
                    'email' => $item->employee->user->email,
                ] : null,
            ]),
            'lines' => PayrollItemLineResource::collection($this->whenLoaded('lines')),
            'payroll_run' => $this->whenLoaded('payrollRun', fn () => $item->payrollRun === null ? null : [
                'id' => $item->payrollRun->id,
                'reference' => $item->payrollRun->reference,
                'period_start' => $item->payrollRun->period_start?->toDateString(),
                'period_end' => $item->payrollRun->period_end?->toDateString(),
                'status' => $item->payrollRun->status,
            ]),
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
