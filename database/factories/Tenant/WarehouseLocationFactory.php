<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseLocation>
 */
class WarehouseLocationFactory extends Factory
{
    /**
     * @var class-string<WarehouseLocation>
     */
    protected $model = WarehouseLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->bothify('LOC-###??'),
            'aisle' => fake()->optional()->bothify('A-##'),
            'rack' => fake()->optional()->bothify('R-##'),
            'shelf' => fake()->optional()->bothify('S-##'),
            'bin' => fake()->optional()->bothify('B-##'),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate the location is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
