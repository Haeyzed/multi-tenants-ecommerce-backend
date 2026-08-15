<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Catalog\ProductRelationType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRelation>
 */
class ProductRelationFactory extends Factory
{
    /**
     * @var class-string<ProductRelation>
     */
    protected $model = ProductRelation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'related_product_id' => Product::factory(),
            'type' => ProductRelationType::Related,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate an upsell relation.
     */
    public function upsell(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductRelationType::Upsell,
        ]);
    }

    /**
     * Indicate a cross-sell relation.
     */
    public function crossSell(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductRelationType::CrossSell,
        ]);
    }
}
