<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeSalary;
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
        return $employee->salary;
    }

    /**
     * Create or update an employee salary record.
     *
     * @param  array{
     *     base_salary: string|float,
     *     currency?: string|null,
     *     pay_frequency?: PayFrequency|string|null,
     *     effective_from?: string|null
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

        $payload = [
            'base_salary' => Money::add($baseSalary, '0'),
            'currency' => strtoupper((string) ($data['currency'] ?? $this->hrSettings->payrollCurrency())),
            'pay_frequency' => $data['pay_frequency'] ?? $this->hrSettings->payrollFrequency(),
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
        ];

        $salary = $employee->salary()->updateOrCreate(
            ['employee_id' => $employee->id],
            $payload,
        );

        return $salary->fresh() ?? $salary;
    }
}
