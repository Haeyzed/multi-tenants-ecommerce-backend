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

    public function weeklyThresholdMinutes(?Employee $employee): int
    {
        $policy = $this->policyFor($employee);

        if ($policy !== null && $policy->weekly_threshold_minutes > 0) {
            return $policy->weekly_threshold_minutes;
        }

        return $this->hrSettings->isWeeklyOvertimeEnabled()
            ? $this->hrSettings->weeklyOvertimeThresholdMinutes()
            : 0;
    }

    public function weeklyRatePercent(?Employee $employee): int
    {
        $policy = $this->policyFor($employee);

        return $policy !== null && $policy->weekly_rate_percent > 0
            ? $policy->weekly_rate_percent
            : $this->hrSettings->weeklyOvertimeRatePercent();
    }

    /**
     * Weekly overtime minutes in a pay period that were not already counted as daily overtime.
     */
    public function weeklyOvertimeMinutes(Employee $employee, string $periodStart, string $periodEnd): int
    {
        $threshold = $this->weeklyThresholdMinutes($employee);

        if ($threshold <= 0) {
            return 0;
        }

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $periodStart)
            ->whereDate('work_date', '<=', $periodEnd)
            ->whereNotNull('checked_in_at')
            ->whereNotNull('checked_out_at')
            ->get();

        $weeks = [];

        foreach ($records as $record) {
            $date = $record->work_date instanceof Carbon
                ? $record->work_date->copy()
                : Carbon::parse((string) $record->work_date);
            $key = $date->isoWeekYear().'-'.$date->isoWeek();
            $worked = (int) max(0, $record->checked_in_at->diffInMinutes($record->checked_out_at));

            $weeks[$key] ??= ['worked' => 0, 'daily' => 0];
            $weeks[$key]['worked'] += $worked;
            $weeks[$key]['daily'] += (int) $record->overtime_minutes;
        }

        $extra = 0;

        foreach ($weeks as $week) {
            $extra += max(0, $week['worked'] - $threshold - $week['daily']);
        }

        $roundTo = max(1, $this->policyFor($employee)?->round_to_minutes ?? 1);
        $extra = (int) (intdiv($extra + (int) floor($roundTo / 2), $roundTo) * $roundTo);

        return min(10080, $extra);
    }
}
