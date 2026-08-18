<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\OvertimeDayType;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use App\Models\Tenant\OvertimePolicy;
use Illuminate\Support\Carbon;

/**
 * Classifies overtime minutes and rates from schedule, holidays, and policy.
 */
class OvertimeEngine
{
    public function __construct(
        private readonly WorkCalendarService $calendar,
        private readonly HrSettingsService $hrSettings,
    ) {}

    public function policyFor(?Employee $employee): ?OvertimePolicy
    {
        $schedule = $this->calendar->scheduleFor($employee);
        $policy = $schedule?->overtime_policy_id
            ? ($schedule->relationLoaded('overtimePolicy')
                ? $schedule->overtimePolicy
                : OvertimePolicy::query()->find($schedule->overtime_policy_id))
            : null;

        if ($policy !== null && $policy->is_active) {
            return $policy;
        }

        return OvertimePolicy::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    public function classify(?Employee $employee, Carbon $date): OvertimeDayType
    {
        if ($this->calendar->isPublicHoliday($date)) {
            return OvertimeDayType::Holiday;
        }

        if (! $this->calendar->isWorkingDate($employee, $date)) {
            return OvertimeDayType::Weekend;
        }

        return OvertimeDayType::Weekday;
    }

    public function ratePercent(?Employee $employee, Carbon $date): int
    {
        $policy = $this->policyFor($employee);
        $fallback = $this->hrSettings->overtimeRatePercent();

        return match ($this->classify($employee, $date)) {
            OvertimeDayType::Holiday => $policy?->holiday_rate_percent ?? $fallback,
            OvertimeDayType::Weekend => $policy?->weekend_rate_percent ?? $fallback,
            OvertimeDayType::Weekday => $policy?->weekday_rate_percent ?? $fallback,
        };
    }

    public function overtimeMinutes(Employee $employee, Attendance $attendance): int
    {
        if ($attendance->checked_in_at === null || $attendance->checked_out_at === null) {
            return 0;
        }

        $date = $attendance->work_date instanceof Carbon
            ? $attendance->work_date
            : Carbon::parse((string) $attendance->work_date);

        $workedMinutes = (int) max(0, $attendance->checked_in_at->diffInMinutes($attendance->checked_out_at));
        $policy = $this->policyFor($employee);
        $threshold = $policy !== null && $policy->daily_threshold_minutes > 0
            ? $policy->daily_threshold_minutes
            : $this->calendar->scheduledMinutes($employee, $date);

        $overtime = max(0, $workedMinutes - $threshold);
        $roundTo = max(1, $policy?->round_to_minutes ?? 1);
        $overtime = (int) (intdiv($overtime + (int) floor($roundTo / 2), $roundTo) * $roundTo);

        $maxDaily = $policy?->max_daily_minutes ?? 0;

        if ($maxDaily > 0) {
            $overtime = min($overtime, $maxDaily);
        }

        return min(1440, $overtime);
    }
}
