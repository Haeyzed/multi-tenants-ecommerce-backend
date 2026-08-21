<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\PayFrequency;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalaryRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalaryRevision>
 */
class EmployeeSalaryRevisionFactory extends Factory
{
    /**
     * @var class-string<EmployeeSalaryRevision>
     */
    protected $model = EmployeeSalaryRevision::class;

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
            'effective_from' => now()->subMonths(6)->toDateString(),
            'effective_to' => now()->subMonth()->toDateString(),
            'components' => [],
        ];
    }
}
