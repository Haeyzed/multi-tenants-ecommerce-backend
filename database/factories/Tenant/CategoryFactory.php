<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @var class-string<Category>
     */
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Nest under a parent category.
     */
    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Indicate the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
