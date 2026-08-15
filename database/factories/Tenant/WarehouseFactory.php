<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * @var class-string<Warehouse>
     */
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Warehouse',
            'code' => fake()->unique()->bothify('WH-###??'),
            'description' => fake()->optional()->sentence(),
            'address' => fake()->optional()->streetAddress(),
            'country_id' => null,
            'state_id' => null,
            'city_id' => null,
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate this is the default warehouse.
     */
    public function default(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate the warehouse is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
