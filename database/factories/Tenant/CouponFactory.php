<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Commerce\CouponType;
use App\Models\Tenant\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @var class-string<Coupon>
     */
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('SAVE-####')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'type' => CouponType::Percentage,
            'value' => '10.00',
            'minimum_order_amount' => '0.00',
            'maximum_discount' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function fixed(string $amount = '25.00'): static
    {
        return $this->state(fn (): array => [
            'type' => CouponType::Fixed,
            'value' => $amount,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
