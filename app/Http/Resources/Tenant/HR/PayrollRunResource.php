<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollRun
 */
class PayrollRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PayrollRun $run */
        $run = $this->resource;

        return [
            'id' => $run->id,
            'payroll_period_id' => $run->payroll_period_id,
            'reference' => $run->reference,
            'period_start' => $run->period_start?->toDateString(),
            'period_end' => $run->period_end?->toDateString(),
            'status' => $run->status,
            'currency' => $run->currency,
            'gross_total' => $run->gross_total,
            'deduction_total' => $run->deduction_total,
            'net_total' => $run->net_total,
            'employee_count' => $run->employee_count,
            'processed_at' => $run->processed_at,
            'paid_at' => $run->paid_at,
            'processed_by' => $run->processed_by,
            'paid_by' => $run->paid_by,
            'notes' => $run->notes,
            'items' => PayrollItemResource::collection($this->whenLoaded('items')),
            'payroll_period' => $this->whenLoaded('payrollPeriod', fn () => $run->payrollPeriod === null ? null : [
                'id' => $run->payrollPeriod->id,
                'period_start' => $run->payrollPeriod->period_start?->toDateString(),
                'period_end' => $run->payrollPeriod->period_end?->toDateString(),
                'payment_date' => $run->payrollPeriod->payment_date?->toDateString(),
                'frequency' => $run->payrollPeriod->frequency,
            ]),
            'processed_by_user' => $this->whenLoaded('processedByUser', fn () => $run->processedByUser === null ? null : [
                'id' => $run->processedByUser->id,
                'first_name' => $run->processedByUser->first_name,
                'last_name' => $run->processedByUser->last_name,
                'email' => $run->processedByUser->email,
            ]),
            'paid_by_user' => $this->whenLoaded('paidByUser', fn () => $run->paidByUser === null ? null : [
                'id' => $run->paidByUser->id,
                'first_name' => $run->paidByUser->first_name,
                'last_name' => $run->paidByUser->last_name,
                'email' => $run->paidByUser->email,
            ]),
            'created_at' => $run->created_at,
            'updated_at' => $run->updated_at,
        ];
    }
}
