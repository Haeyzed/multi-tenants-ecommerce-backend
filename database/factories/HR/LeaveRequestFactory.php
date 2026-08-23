<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType as LeaveTypeCode;
use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\HR\LeaveRequest;
use App\Models\Tenant\HR\LeaveType;
use App\Services\Tenant\HR\LeaveTypeService;
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
            'leave_type_id' => fn (): int => $this->resolveLeaveTypeId(LeaveTypeCode::Annual->value),
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

    protected function resolveLeaveTypeId(string $code): int
    {
        app(LeaveTypeService::class)->ensureDefaults();

        $existing = LeaveType::query()->where('code', $code)->value('id');

        if (is_int($existing)) {
            return $existing;
        }

        return LeaveType::factory()->create(['code' => $code])->id;
    }
}
