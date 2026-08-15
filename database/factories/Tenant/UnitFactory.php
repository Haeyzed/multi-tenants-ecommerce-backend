<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @var class-string<Unit>
     */
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Piece', 'Kilogram', 'Gram', 'Liter', 'Box', 'Pack']);

        return [
            'name' => $name,
            'short_name' => strtoupper(substr($name, 0, 3)),
            'code' => fake()->unique()->bothify('U-###??'),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate the unit is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
