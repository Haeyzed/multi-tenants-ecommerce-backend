<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @var class-string<Attendance>
     */
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkedIn = now()->setTime(9, 0);

        return [
            'employee_id' => Employee::factory(),
            'work_date' => now()->subDays(fake()->numberBetween(0, 60))->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => $checkedIn,
            'checked_out_at' => $checkedIn->copy()->setTime(17, 0),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Open clock-in without a clock-out.
     */
    public function clockedIn(): static
    {
        return $this->state(fn (): array => [
            'work_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);
    }
}
