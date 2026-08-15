<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Catalog\ProductReviewStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    /**
     * @var class-string<ProductReview>
     */
    protected $model = ProductReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional()->sentence(4),
            'content' => fake()->paragraph(),
            'status' => ProductReviewStatus::Pending,
            'verified_purchase' => false,
            'approved_at' => null,
        ];
    }

    /**
     * Indicate the review is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductReviewStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate a verified purchase review.
     */
    public function verifiedPurchase(): static
    {
        return $this->state(fn (): array => [
            'verified_purchase' => true,
        ]);
    }
}
