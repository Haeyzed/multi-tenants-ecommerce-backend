<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Loyalty\LoyaltyAccountStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\LoyaltyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyAccount>
 */
class LoyaltyAccountFactory extends Factory
{
    /**
     * @var class-string<LoyaltyAccount>
     */
    protected $model = LoyaltyAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_redeemed' => 0,
            'status' => LoyaltyAccountStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => LoyaltyAccountStatus::Suspended,
        ]);
    }
}
