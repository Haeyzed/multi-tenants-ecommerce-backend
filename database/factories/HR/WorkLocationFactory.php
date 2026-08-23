<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Tenant\HR\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLocation>
 */
class WorkLocationFactory extends Factory
{
    /**
     * @var class-string<WorkLocation>
     */
    protected $model = WorkLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Office',
            'code' => fake()->unique()->bothify('LOC-###'),
            'address' => fake()->optional()->streetAddress(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
