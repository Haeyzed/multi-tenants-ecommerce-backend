<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @var class-string<ProductVariant>
     */
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->optional()->words(2, true),
            'sku' => fake()->unique()->bothify('SKU-########'),
            'barcode' => fake()->optional()->ean13(),
            'unit_id' => Unit::factory(),
            'is_active' => true,
            'weight' => fake()->optional()->randomFloat(3, 0.1, 50),
            'length' => fake()->optional()->randomFloat(3, 1, 100),
            'width' => fake()->optional()->randomFloat(3, 1, 100),
            'height' => fake()->optional()->randomFloat(3, 1, 100),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate the variant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
