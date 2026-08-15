<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Accounting\AccountType;
use App\Models\Tenant\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @var class-string<Account>
     */
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(3, true),
            'type' => fake()->randomElement(AccountType::cases()),
            'is_system' => false,
            'is_active' => true,
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate this is a system account.
     */
    public function system(): static
    {
        return $this->state(fn (): array => [
            'is_system' => true,
        ]);
    }
}
