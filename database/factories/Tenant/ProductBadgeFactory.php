<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\ProductBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBadge>
 */
class ProductBadgeFactory extends Factory
{
    /**
     * @var class-string<ProductBadge>
     */
    protected $model = ProductBadge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->optional()->hexColor(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate the badge is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
