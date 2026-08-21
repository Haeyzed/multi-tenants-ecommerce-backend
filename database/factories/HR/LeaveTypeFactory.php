<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\LeaveType as LeaveTypeCode;
use App\Models\HR\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @var class-string<LeaveType>
     */
    protected $model = LeaveType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->lexify('leave_????'),
            'is_paid' => true,
            'is_active' => true,
            'default_days' => fake()->numberBetween(0, 21),
        ];
    }

    /**
     * Unpaid leave type matching the default unpaid code.
     */
    public function unpaid(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Unpaid',
            'code' => LeaveTypeCode::Unpaid->value,
            'is_paid' => false,
            'default_days' => 0,
        ]);
    }
}
