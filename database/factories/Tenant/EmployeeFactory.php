<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @var class-string<Employee>
     */
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'department_id' => null,
            'designation_id' => null,
            'job_title' => fake()->optional()->jobTitle(),
            'employee_number' => fake()->boolean()
                ? fake()->unique()->bothify('EMP-####')
                : null,
            'employment_status' => EmploymentStatus::Active,
            'hired_at' => fake()->optional()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Assign a department.
     */
    public function forDepartment(?Department $department = null): static
    {
        return $this->state(fn (): array => [
            'department_id' => ($department ?? Department::factory()->create())->id,
        ]);
    }

    /**
     * Terminated employment status.
     */
    public function terminated(): static
    {
        return $this->state(fn (): array => [
            'employment_status' => EmploymentStatus::Terminated,
        ]);
    }
}
