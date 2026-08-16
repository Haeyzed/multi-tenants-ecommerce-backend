<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Pos\PosCashMovementType;
use App\Models\Tenant\PosCashMovement;
use App\Models\Tenant\PosSession;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosCashMovement>
 */
class PosCashMovementFactory extends Factory
{
    /**
     * @var class-string<PosCashMovement>
     */
    protected $model = PosCashMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_session_id' => PosSession::factory(),
            'type' => PosCashMovementType::CashIn,
            'amount' => fake()->randomFloat(2, 1, 100),
            'reason' => fake()->optional()->sentence(),
            'user_id' => User::factory(),
        ];
    }
}
