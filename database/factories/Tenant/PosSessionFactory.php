<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Pos\PosSessionStatus;
use App\Models\Tenant\PosSession;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSession>
 */
class PosSessionFactory extends Factory
{
    /**
     * @var class-string<PosSession>
     */
    protected $model = PosSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_terminal_id' => PosTerminal::factory(),
            'user_id' => User::factory(),
            'status' => PosSessionStatus::Open,
            'opened_at' => now(),
            'closed_at' => null,
            'opening_cash' => '100.00',
            'closing_cash' => null,
            'expected_cash' => null,
            'actual_cash' => null,
            'cash_difference' => null,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PosSessionStatus::Closed,
            'closed_at' => now(),
            'closing_cash' => '100.00',
            'expected_cash' => '100.00',
            'actual_cash' => '100.00',
            'cash_difference' => '0.00',
        ]);
    }
}
