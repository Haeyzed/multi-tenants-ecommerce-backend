<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Enums\Tenant\HR\PayrollLineType;
use App\Enums\Tenant\HR\SalaryComponentCalculation;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeSalary;
use App\Models\Tenant\EmployeeSalaryRevision;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Employee compensation configuration.
 */
class EmployeeSalaryService
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * Show the employee's current salary, if configured.
     */
    public function show(Employee $employee): ?EmployeeSalary
    {
        return $employee->salary()->with('components')->first();
    }

    /**
     * Create or update an employee salary record.
     *
     * @param  array{
     *     base_salary: string|float,
     *     currency?: string|null,
     *     pay_frequency?: PayFrequency|string|null,
     *     effective_from?: string|null,
     *     components?: list<array{
     *         type: PayrollLineType|string,
     *         calculation?: SalaryComponentCalculation|string|null,
     *         code: string,
     *         label: string,
     *         amount: string|float|int,
     *         is_tax?: bool|null,
     *         sort_order?: int|null
     *     }>
     * }  $data
     *
     * @throws ValidationException
     */
    public function upsert(Employee $employee, array $data): EmployeeSalary
    {
        $this->hrSettings->assertPayrollEnabled();

        $baseSalary = (string) $data['base_salary'];

        if (bccomp($baseSalary, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'base_salary' => ['Base salary must be greater than zero.'],
            ]);
        }

        $existing = $this->show($employee);

        $payload = [
            'base_salary' => Money::add($baseSalary, '0'),
            'currency' => strtoupper((string) ($data['currency'] ?? $this->hrSettings->payrollCurrency())),
            'pay_frequency' => $data['pay_frequency'] ?? $this->hrSettings->payrollFrequency(),
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
        ];

        if ($existing !== null) {
            $this->recordRevision($existing, (string) $payload['effective_from']);
        }

        $salary = $employee->salary()->updateOrCreate(
            ['employee_id' => $employee->id],
            $payload,
        );

        if (array_key_exists('components', $data) && is_array($data['components'])) {
            $this->syncComponents($salary, $data['components']);
        }

        return $salary->fresh(['components']) ?? $salary;
    }

    /**
     * @return list<EmployeeSalaryRevision>
     */
    public function history(Employee $employee): array
    {
        return $employee->salaryRevisions()->get()->all();
    }

    protected function recordRevision(EmployeeSalary $salary, string $effectiveTo): void
    {
        $salary->loadMissing('components');

        EmployeeSalaryRevision::query()->create([
            'employee_id' => $salary->employee_id,
            'base_salary' => $salary->base_salary,
            'currency' => $salary->currency,
            'pay_frequency' => $salary->pay_frequency,
            'effective_from' => $salary->effective_from?->toDateString() ?? $effectiveTo,
            'effective_to' => $effectiveTo,
            'components' => $salary->components->map(fn ($component): array => [
                'type' => $component->type instanceof PayrollLineType ? $component->type->value : $component->type,
                'calculation' => $component->calculation instanceof SalaryComponentCalculation ? $component->calculation->value : $component->calculation,
                'code' => $component->code,
                'label' => $component->label,
                'amount' => $component->amount,
                'is_tax' => $component->is_tax,
                'sort_order' => $component->sort_order,
            ])->values()->all(),
        ]);
    }

    /**
     * @param  list<array{
     *     type: PayrollLineType|string,
     *     calculation?: SalaryComponentCalculation|string|null,
     *     code: string,
     *     label: string,
     *     amount: string|float|int,
     *     is_tax?: bool|null,
     *     sort_order?: int|null
     * }>  $components
     */
    protected function syncComponents(EmployeeSalary $salary, array $components): void
    {
        $salary->components()->delete();

        foreach ($components as $index => $component) {
            $type = $component['type'] instanceof PayrollLineType
                ? $component['type']
                : PayrollLineType::from((string) $component['type']);

            if ((bool) ($component['is_tax'] ?? false)) {
                $type = PayrollLineType::Deduction;
            }

            $calculation = ($component['calculation'] ?? null) instanceof SalaryComponentCalculation
                ? $component['calculation']
                : SalaryComponentCalculation::tryFrom((string) ($component['calculation'] ?? 'fixed')) ?? SalaryComponentCalculation::Fixed;

            $salary->components()->create([
                'type' => $type,
                'calculation' => $calculation,
                'code' => strtolower((string) $component['code']),
                'label' => $component['label'],
                'amount' => Money::add((string) $component['amount'], '0'),
                'is_tax' => (bool) ($component['is_tax'] ?? false),
                'sort_order' => (int) ($component['sort_order'] ?? $index),
            ]);
        }
    }
}
