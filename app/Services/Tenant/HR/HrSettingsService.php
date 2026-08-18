<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\PayFrequency;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Feature\FeatureAccessService;
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
        private readonly FeatureAccessService $features,
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

    public function payrollTaxPayableAccountId(): ?int
    {
        $id = $this->value('hr.payroll.tax_payable_account_id');

        return $id === null ? null : (int) $id;
    }

    public function payrollDeductionPayableAccountId(): ?int
    {
        $id = $this->value('hr.payroll.deduction_payable_account_id');

        return $id === null ? null : (int) $id;
    }

    public function isPayrollTaxEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.tax_enabled');
    }

    public function payrollTaxTableId(): ?int
    {
        $id = $this->value('hr.payroll.tax_table_id');

        return $id === null ? null : (int) $id;
    }

    public function isPayrollTaxYtdEnabled(): bool
    {
        return $this->isPayrollTaxEnabled() && (bool) $this->value('hr.payroll.tax_ytd_enabled');
    }

    public function payrollTaxYearStartMonth(): int
    {
        $month = (int) $this->value('hr.payroll.tax_year_start_month');

        return ($month >= 1 && $month <= 12) ? $month : 1;
    }

    public function isPensionEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.pension_enabled');
    }

    public function pensionEmployeePercent(): string
    {
        return $this->percentSetting('hr.payroll.pension_employee_percent', '8.00');
    }

    public function pensionEmployerPercent(): string
    {
        return $this->percentSetting('hr.payroll.pension_employer_percent', '10.00');
    }

    public function isNhfEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.nhf_enabled');
    }

    public function nhfPercent(): string
    {
        return $this->percentSetting('hr.payroll.nhf_percent', '2.50');
    }

    public function isNsitfEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.nsitf_enabled');
    }

    public function nsitfPercent(): string
    {
        return $this->percentSetting('hr.payroll.nsitf_percent', '1.00');
    }

    public function isNibssEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.nibss.enabled');
    }

    public function nibssBaseUrl(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.base_url');
    }

    public function nibssApiKey(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.api_key');
    }

    public function nibssInstitutionCode(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.institution_code');
    }

    public function nibssOriginatorAccount(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.originator_account');
    }

    public function nibssOriginatorBankCode(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.originator_bank_code');
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

    public function isWeeklyOvertimeEnabled(): bool
    {
        return $this->isOvertimeEnabled() && (bool) $this->value('hr.overtime.weekly_enabled');
    }

    public function weeklyOvertimeThresholdMinutes(): int
    {
        return max(0, (int) $this->value('hr.overtime.weekly_threshold_minutes'));
    }

    public function weeklyOvertimeRatePercent(): int
    {
        $rate = (int) $this->value('hr.overtime.weekly_rate_percent');

        return $rate > 0 ? $rate : 150;
    }

    public function gpsRequired(): bool
    {
        return $this->isAttendanceEnabled() && (bool) $this->value('hr.attendance.gps_required');
    }

    public function geofenceLatitude(): ?float
    {
        $value = $this->value('hr.attendance.geofence_latitude');

        return $value === null || $value === '' ? null : (float) $value;
    }

    public function geofenceLongitude(): ?float
    {
        $value = $this->value('hr.attendance.geofence_longitude');

        return $value === null || $value === '' ? null : (float) $value;
    }

    public function geofenceRadiusMeters(): int
    {
        return max(0, (int) $this->value('hr.attendance.geofence_radius_meters'));
    }

    public function biometricRequired(): bool
    {
        return $this->isAttendanceEnabled() && (bool) $this->value('hr.attendance.biometric_required');
    }

    public function isRecruitmentEnabled(): bool
    {
        return $this->isEnabled()
            && (bool) $this->value('hr.recruitment.enabled')
            && $this->planAllowsRecruitment();
    }

    public function isPublicJobListingsEnabled(): bool
    {
        return $this->isRecruitmentEnabled() && (bool) $this->value('hr.recruitment.public_listings_enabled');
    }

    public function isPublicJobApplicationsEnabled(): bool
    {
        return $this->isPublicJobListingsEnabled() && (bool) $this->value('hr.recruitment.public_applications_enabled');
    }

    public function offerApprovalRequired(): bool
    {
        return (bool) $this->value('hr.recruitment.offer_approval_required');
    }

    public function interviewRequiredBeforeOffer(): bool
    {
        return (bool) $this->value('hr.recruitment.interview_required_before_offer');
    }

    public function defaultHireRole(): string
    {
        $role = trim((string) $this->value('hr.recruitment.default_hire_role'));

        return $role !== '' ? $role : 'employee';
    }

    public function notifyRecruitment(): bool
    {
        return (bool) $this->value('hr.notifications.recruitment');
    }

    public function onlineInterviewsEnabled(): bool
    {
        return $this->isRecruitmentEnabled() && (bool) $this->value('hr.interviews.online_enabled');
    }

    public function defaultInterviewMeetingProvider(): string
    {
        $provider = strtolower(trim((string) $this->value('hr.interviews.default_provider')));

        return $provider !== '' ? $provider : 'manual';
    }

    public function autoCreateInterviewMeeting(): bool
    {
        return (bool) $this->value('hr.interviews.auto_create_meeting');
    }

    public function autoSyncInterviewMeeting(): bool
    {
        return (bool) $this->value('hr.interviews.auto_sync_meeting');
    }

    public function cancelExternalInterviewMeeting(): bool
    {
        return (bool) $this->value('hr.interviews.cancel_external_meeting');
    }

    public function defaultInterviewDurationMinutes(): int
    {
        $minutes = (int) $this->value('hr.interviews.default_duration_minutes');

        return ($minutes >= 5 && $minutes <= 480) ? $minutes : 60;
    }

    /**
     * Hours before scheduled_at at which interview reminders are sent.
     *
     * @return list<int>
     */
    public function interviewReminderHours(): array
    {
        $raw = (string) $this->value('hr.interviews.reminder_hours');
        $hours = array_values(array_unique(array_filter(
            array_map(static fn (string $hour): int => (int) trim($hour), explode(',', $raw)),
            static fn (int $hour): bool => $hour > 0 && $hour <= 168,
        )));

        sort($hours);

        return $hours;
    }

    public function interviewTimezone(): string
    {
        $tenant = tenant();
        $timezone = null;

        if ($tenant instanceof Tenant) {
            $timezone = $tenant->profile?->timezone;
        }

        if (! is_string($timezone) || $timezone === '') {
            $timezone = (string) config('app.timezone', 'UTC');
        }

        return $timezone !== '' ? $timezone : 'UTC';
    }

    public function isPerformanceEnabled(): bool
    {
        return $this->isEnabled() && (bool) $this->value('hr.performance.enabled');
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

    /**
     * @throws ValidationException
     */
    public function assertRecruitmentEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->isRecruitmentEnabled()) {
            throw ValidationException::withMessages([
                'recruitment' => ['Recruitment is disabled in HR settings.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertPublicJobListingsEnabled(): void
    {
        $this->assertRecruitmentEnabled();

        if (! $this->isPublicJobListingsEnabled()) {
            throw ValidationException::withMessages([
                'recruitment' => ['Public job listings are disabled in HR settings.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertPublicJobApplicationsEnabled(): void
    {
        $this->assertPublicJobListingsEnabled();

        if (! $this->isPublicJobApplicationsEnabled()) {
            throw ValidationException::withMessages([
                'recruitment' => ['Public job applications are disabled in HR settings.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertPerformanceEnabled(): void
    {
        $this->assertModuleEnabled();

        if (! $this->isPerformanceEnabled()) {
            throw ValidationException::withMessages([
                'performance' => ['Performance reviews are disabled in HR settings.'],
            ]);
        }
    }

    protected function percentSetting(string $key, string $fallback): string
    {
        $value = $this->value($key);

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $fallback;
        }

        $normalized = number_format((float) $value, 2, '.', '');

        return bccomp($normalized, '0', 2) < 0 ? $fallback : $normalized;
    }

    protected function nullableString(string $key): ?string
    {
        $value = trim((string) ($this->value($key) ?? ''));

        return $value === '' ? null : $value;
    }

    protected function planAllowsRecruitment(): bool
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || $tenant->activeSubscription() === null) {
            return true;
        }

        if ($this->features->has($tenant, 'recruitment')) {
            return true;
        }

        $slugs = $this->features->featuresForTenant($tenant)->pluck('slug');

        if (! $slugs->contains('recruitment')) {
            return $this->features->has($tenant, 'hr') || $slugs->isEmpty();
        }

        return false;
    }

    protected function value(string $key): mixed
    {
        if ($this->settings === []) {
            $this->settings = $this->all();
        }

        return $this->settings[$key] ?? null;
    }
}
