<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Marketplace\SellerOfferStatus;
use App\Models\Tenant\Product;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerOffer>
 */
class SellerOfferFactory extends Factory
{
    /**
     * @var class-string<SellerOffer>
     */
    protected $model = SellerOffer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory()->sellable(),
            'product_id' => Product::factory()->active(),
            'product_variant_id' => null,
            'sku' => fake()->optional()->bothify('OFF-####'),
            'currency' => 'NGN',
            'price' => fake()->randomFloat(2, 10, 5000),
            'compare_at_price' => null,
            'cost' => null,
            'status' => SellerOfferStatus::Active,
            'metadata' => null,
        ];
    }

    /**
     * Inactive offer.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerOfferStatus::Inactive,
        ]);
    }
}
