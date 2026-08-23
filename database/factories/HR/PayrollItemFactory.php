<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\HR\PayrollItem;
use App\Models\Tenant\HR\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollItem>
 */
class PayrollItemFactory extends Factory
{
    /**
     * @var class-string<PayrollItem>
     */
    protected $model = PayrollItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = fake()->randomFloat(2, 50000, 500000);
        $deductions = fake()->randomFloat(2, 0, 5000);
        $net = max(0, $base - $deductions);

        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employee_id' => Employee::factory(),
            'base_salary' => number_format($base, 2, '.', ''),
            'gross_pay' => number_format($base, 2, '.', ''),
            'deduction_total' => number_format($deductions, 2, '.', ''),
            'net_pay' => number_format($net, 2, '.', ''),
            'working_days' => 22,
            'absent_days' => 0,
            'unpaid_leave_days' => 0,
        ];
    }
}
