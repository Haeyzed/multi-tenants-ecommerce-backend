<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyProgram>
 */
class LoyaltyProgramFactory extends Factory
{
    /**
     * @var class-string<LoyaltyProgram>
     */
    protected $model = LoyaltyProgram::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Loyalty Rewards',
            'is_active' => true,
            'points_per_currency_unit' => '1.00',
            'redemption_points_per_currency' => 100,
            'min_redemption_points' => 100,
            'max_redemption_percent' => '100.00',
            'earn_on_order_paid' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
