<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Department;
use App\Models\Tenant\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    /**
     * @var class-string<Designation>
     */
    protected $model = Designation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => null,
            'name' => fake()->unique()->jobTitle(),
            'code' => fake()->unique()->bothify('DES-###'),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Scope the designation to a department.
     */
    public function forDepartment(?Department $department = null): static
    {
        return $this->state(fn (): array => [
            'department_id' => ($department ?? Department::factory()->create())->id,
        ]);
    }

    /**
     * Inactive designation.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
