<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * @var class-string<ShippingMethod>
     */
    protected $model = ShippingMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Shipping',
            'code' => fake()->unique()->bothify('SHIP-###??'),
            'description' => fake()->optional()->sentence(),
            'amount' => fake()->randomFloat(2, 0, 50),
            'min_order_amount' => fake()->optional()->randomFloat(2, 0, 100),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'estimated_days_min' => fake()->optional()->numberBetween(1, 3),
            'estimated_days_max' => fake()->optional()->numberBetween(4, 14),
        ];
    }

    /**
     * Indicate the shipping method is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
