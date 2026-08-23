<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\HR\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    /**
     * @var class-string<EmployeeSalary>
     */
    protected $model = EmployeeSalary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'base_salary' => fake()->randomFloat(2, 50000, 500000),
            'currency' => 'NGN',
            'pay_frequency' => PayFrequency::Monthly,
            'effective_from' => now()->subMonths(3)->toDateString(),
        ];
    }
}
