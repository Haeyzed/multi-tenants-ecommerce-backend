<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\HR\PublicHoliday;
use App\Models\Tenant\HR\WorkSchedule;
use App\Models\Tenant\HR\WorkScheduleDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves employee working days from a schedule, or HR settings as fallback.
 */
class WorkCalendarService
{
    /**
     * @var Collection<int, PublicHoliday>|null
     */
    protected ?Collection $holidays = null;

    /**
     * Create a new class instance.
     *
     * @param  HrSettingsService  $hrSettings
     */
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * Schedule for.
     *
     * @param  ?Employee  $employee
     * @return ?WorkSchedule
     */
    public function scheduleFor(?Employee $employee): ?WorkSchedule
    {
        $schedule = $employee?->work_schedule_id
            ? $employee->relationLoaded('workSchedule')
                ? $employee->workSchedule
                : WorkSchedule::query()->with('days')->find($employee->work_schedule_id)
            : null;

        if ($schedule !== null && $schedule->is_active) {
            $schedule->loadMissing('days');

            return $schedule;
        }

        $default = WorkSchedule::query()
            ->with('days')
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        return $default;
    }

    /**
     * Day for.
     *
     * @param  ?Employee  $employee
     * @param  Carbon  $date
     * @return ?WorkScheduleDay
     */
    public function dayFor(?Employee $employee, Carbon $date): ?WorkScheduleDay
    {
        $schedule = $this->scheduleFor($employee);

        if ($schedule === null) {
            return null;
        }

        return $schedule->days->first(
            fn (WorkScheduleDay $day): bool => $day->weekday === $date->isoWeekday(),
        );
    }

    /**
     * Is working date.
     *
     * @param  ?Employee  $employee
     * @param  Carbon  $date
     * @return bool
     */
    public function isWorkingDate(?Employee $employee, Carbon $date): bool
    {
        $schedule = $this->scheduleFor($employee);

        if ($schedule === null) {
            return $this->hrSettings->isWorkingDate($date);
        }

        return $this->dayFor($employee, $date) !== null;
    }

    /**
     * Scheduled minutes.
     *
     * @param  ?Employee  $employee
     * @param  Carbon  $date
     * @return int
     */
    public function scheduledMinutes(?Employee $employee, Carbon $date): int
    {
        $day = $this->dayFor($employee, $date);

        if ($day !== null) {
            return $day->scheduledMinutes();
        }

        if ($this->scheduleFor($employee) !== null) {
            return 0;
        }

        return $this->hrSettings->isWorkingDate($date)
            ? $this->hrSettings->workingHoursPerDay() * 60
            : 0;
    }

    /**
     * Start time.
     *
     * @param  ?Employee  $employee
     * @param  Carbon  $date
     * @return string
     */
    public function startTime(?Employee $employee, Carbon $date): string
    {
        $day = $this->dayFor($employee, $date);

        if ($day !== null) {
            return substr((string) $day->start_time, 0, 5);
        }

        return $this->hrSettings->workStartTime();
    }

    /**
     * Is public holiday.
     *
     * @param  Carbon  $date
     * @return bool
     */
    public function isPublicHoliday(Carbon $date): bool
    {
        return $this->holidays()->contains(function (PublicHoliday $holiday) use ($date): bool {
            if ($holiday->observed_on->isSameDay($date)) {
                return true;
            }

            return $holiday->repeats_annually
                && $holiday->observed_on->month === $date->month
                && $holiday->observed_on->day === $date->day;
        });
    }

    /**
     * Holidays.
     *
     * @return Collection<int, PublicHoliday>
     */
    protected function holidays(): Collection
    {
        if ($this->holidays === null) {
            $this->holidays = PublicHoliday::query()->orderBy('observed_on')->get();
        }

        return $this->holidays;
    }
}
