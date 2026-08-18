<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\HR\PayFrequency;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeSalary;
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
