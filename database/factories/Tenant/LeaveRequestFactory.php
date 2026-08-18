<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @var class-string<LeaveRequest>
     */
    protected $model = LeaveRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 14))->startOfDay();

        return [
            'employee_id' => Employee::factory(),
            'type' => LeaveType::Annual->value,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'status' => LeaveStatus::Pending,
            'reason' => fake()->optional()->sentence(),
            'reviewer_id' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }

    /**
     * Approved leave request.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => LeaveStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
