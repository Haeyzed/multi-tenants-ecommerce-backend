<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Catalog\CollectionStatus;
use App\Enums\Tenant\Catalog\CollectionType;
use App\Models\Tenant\ProductCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCollection>
 */
class ProductCollectionFactory extends Factory
{
    /**
     * @var class-string<ProductCollection>
     */
    protected $model = ProductCollection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'type' => CollectionType::Manual,
            'status' => CollectionStatus::Draft,
            'sort_order' => fake()->numberBetween(0, 100),
            'published_at' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Indicate the collection is active and published.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => CollectionStatus::Active,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate the collection is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => CollectionStatus::Inactive,
        ]);
    }
}
