<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use App\Models\Tenant\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    /**
     * @var class-string<Seller>
     */
    protected $model = Seller::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'description' => fake()->optional()->sentence(),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'status' => SellerStatus::Inactive,
            'verification_status' => SellerVerificationStatus::Pending,
        ];
    }

    /**
     * Approved and active seller that may sell.
     */
    public function sellable(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerStatus::Active,
            'verification_status' => SellerVerificationStatus::Approved,
        ]);
    }

    /**
     * Under review verification.
     */
    public function underReview(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => SellerVerificationStatus::UnderReview,
            'status' => SellerStatus::Inactive,
        ]);
    }
}
