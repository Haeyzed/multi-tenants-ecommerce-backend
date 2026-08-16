<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\SellerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerGroup>
 */
class SellerGroupFactory extends Factory
{
    /**
     * @var class-string<SellerGroup>
     */
    protected $model = SellerGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Group',
            'description' => fake()->optional()->sentence(),
            'commission_type' => null,
            'commission_rate' => null,
            'commission_fixed_amount' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate the group is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
