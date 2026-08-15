<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Catalog\ProductStatus;
use App\Enums\Tenant\Catalog\ProductType;
use App\Enums\Tenant\Catalog\ProductVisibility;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Product;
use App\Models\Tenant\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @var class-string<Product>
     */
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'unit_id' => Unit::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'short_description' => fake()->optional()->sentence(),
            'type' => ProductType::Simple,
            'status' => ProductStatus::Draft,
            'visibility' => ProductVisibility::Public,
            'has_variants' => false,
            'is_featured' => false,
            'tax_class' => fake()->optional()->randomElement(['standard', 'reduced', 'zero']),
            'weight' => fake()->optional()->randomFloat(3, 0.1, 50),
            'length' => fake()->optional()->randomFloat(3, 1, 100),
            'width' => fake()->optional()->randomFloat(3, 1, 100),
            'height' => fake()->optional()->randomFloat(3, 1, 100),
            'published_at' => null,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate the product is variable with variants.
     */
    public function variable(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductType::Variable,
            'has_variants' => true,
        ]);
    }

    /**
     * Indicate the product is active and published.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }
}
