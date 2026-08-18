<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\PayFrequency;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-scoped HR configuration backed by the commerce settings KV store.
 */
class HrSettingsService
{
    public const string DOMAIN = 'hr';

    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        private readonly CommerceSettingService $commerceSettings,
        private array $settings = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->commerceSettings->getDomain(self::DOMAIN);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function update(array $values): array
    {
        $this->settings = [];

        return $this->commerceSettings->updateDomain(self::DOMAIN, $values);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->value('hr.enabled');
    }

    public function isAttendanceEnabled(): bool
    {
        return $this->isEnabled() && (bool) $this->value('hr.attendance.enabled');
    }

    public function isLeaveEnabled(): bool
    {
        return $this->isEnabled() && (bool) $this->value('hr.leave.enabled');
    }

    public function isPayrollEnabled(): bool
    {
        return $this->isEnabled() && (bool) $this->value('hr.payroll.enabled');
    }

    public function employeeCodePrefix(): string
    {
        $prefix = strtoupper(trim((string) $this->value('hr.employee_code_prefix')));

        return $prefix !== '' ? $prefix : 'EMP';
    }

    public function defaultEmploymentStatus(): EmploymentStatus
    {
        $value = (string) $this->value('hr.default_employment_status');

        return EmploymentStatus::tryFrom($value) ?? EmploymentStatus::Active;
    }

    /**
     * ISO-8601 weekdays (1 = Monday … 7 = Sunday).
     *
     * @return list<int>
     */
    public function workingDays(): array
    {
        $raw = (string) $this->value('hr.working_days');
        $days = array_values(array_filter(
            array_map(static fn (string $day): int => (int) trim($day), explode(',', $raw)),
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        ));

        return $days !== [] ? $days : [1, 2, 3, 4, 5];
    }

    public function isWorkingDate(Carbon $date): bool
    {
        return in_array($date->isoWeekday(), $this->workingDays(), true);
    }

    public function workStartTime(): string
    {
        $time = (string) $this->value('hr.work_start_time');

        return preg_match('/^\d{2}:\d{2}$/', $time) === 1 ? $time : '09:00';
    }

    public function lateToleranceMinutes(): int
    {
        return max(0, (int) $this->value('hr.late_tolerance_minutes'));
    }

    public function leaveApprovalRequired(): bool
    {
        return (bool) $this->value('hr.leave.approval_required');
    }

    public function maxConsecutiveLeaveDays(): int
    {
        return max(0, (int) $this->value('hr.leave.max_consecutive_days'));
    }

    public function leaveYearStartMonth(): int
    {
        $month = (int) $this->value('hr.leave.year_start_month');

        return ($month >= 1 && $month <= 12) ? $month : 1;
    }

    public function leaveYearForDate(Carbon $date): int
    {
        $startMonth = $this->leaveYearStartMonth();

        return $date->month < $startMonth ? $date->year - 1 : $date->year;
    }

    public function payrollFrequency(): PayFrequency
    {
        $value = (string) $this->value('hr.payroll.frequency');

        return PayFrequency::tryFrom($value) ?? PayFrequency::Monthly;
    }

    public function payrollCurrency(): string
    {
        $currency = strtoupper(trim((string) $this->value('hr.payroll.currency')));

        return strlen($currency) === 3 ? $currency : 'NGN';
    }

    public function payrollApprovalRequired(): bool
    {
        return (bool) $this->value('hr.payroll.approval_required');
    }

    public function payrollPaymentDay(): int
    {
        $day = (int) $this->value('hr.payroll.payment_day');

        return ($day >= 1 && $day <= 28) ? $day : 25;
    }

    public function payrollExpenseAccountId(): ?int
    {
        $id = $this->value('hr.payroll.expense_account_id');

        return $id === null ? null : (int) $id;
    }

    public function payrollPayableAccountId(): ?int
    {
        $id = $this->value('hr.payroll.payable_account_id');

        return $id === null ? null : (int) $id;
    }

    public function isOvertimeEnabled(): bool
    {
        return $this->isAttendanceEnabled() && (bool) $this->value('hr.overtime.enabled');
    }

    public function overtimeRatePercent(): int
    {
        $rate = (int) $this->value('hr.overtime.rate_percent');

        return $rate > 0 ? $rate : 150;
    }

    public function workingHoursPerDay(): int
    {
        $hours = (int) $this->value('hr.working_hours_per_day');

        return ($hours >= 1 && $hours <= 24) ? $hours : 8;
    }

    public function leaveCarryOverEnabled(): bool
    {
        return $this->isLeaveEnabled() && (bool) $this->value('hr.leave.carry_over_enabled');
    }

    public function leaveCarryOverMaxDays(): int
    {
        return max(0, (int) $this->value('hr.leave.carry_over_max_days'));
    }

    public function notifyLeave(): bool
    {
        return (bool) $this->value('hr.notifications.leave');
    }

    public function notifyPayroll(): bool
    {
        return (bool) $this->value('hr.notifications.payroll');
    }

    /**
     * @throws ValidationException
     */
    public function assertModuleEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'hr' => ['The HR module is disabled for this tenant.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertAttendanceEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->isAttendanceEnabled()) {
            throw ValidationException::withMessages([
                'attendance' => ['Attendance is disabled in HR settings.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertLeaveEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->isLeaveEnabled()) {
            throw ValidationException::withMessages([
                'leave' => ['Leave management is disabled in HR settings.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertPayrollEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->isPayrollEnabled()) {
            throw ValidationException::withMessages([
                'payroll' => ['Payroll is disabled in HR settings.'],
            ]);
        }
    }

    protected function value(string $key): mixed
    {
        if ($this->settings === []) {
            $this->settings = $this->all();
        }

        return $this->settings[$key] ?? null;
    }
}
