<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use App\Models\Tenant\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

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
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => SellerStatus::Inactive,
            'verification_status' => SellerVerificationStatus::Pending,
            'remember_token' => Str::random(10),
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

    /**
     * Suspended seller (cannot log in).
     */
    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerStatus::Suspended,
            'verification_status' => SellerVerificationStatus::Approved,
        ]);
    }

    /**
     * Rejected seller (cannot log in).
     */
    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerStatus::Inactive,
            'verification_status' => SellerVerificationStatus::Rejected,
        ]);
    }
}
