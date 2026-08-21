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
     * Create a new class instance.
     *
     * @param  CommerceSettingService  $commerceSettings
     * @param  FeatureAccessService  $features
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        private readonly CommerceSettingService $commerceSettings,
        private readonly FeatureAccessService $features,
        private array $settings = [],
    ) {}

    /**
     * All.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->commerceSettings->getDomain(self::DOMAIN);
    }

    /**
     * Update a resource.
     *
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

    /**
     * Is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->value('hr.enabled');
    }

    /**
     * Is attendance enabled.
     *
     * @return bool
     */
    public function isAttendanceEnabled(): bool
    {
        return $this->isEnabled() && (bool) $this->value('hr.attendance.enabled');
    }

    /**
     * Is leave enabled.
     *
     * @return bool
     */
    public function isLeaveEnabled(): bool
    {
        return $this->isEnabled() && (bool) $this->value('hr.leave.enabled');
    }

    /**
     * Is payroll enabled.
     *
     * @return bool
     */
    public function isPayrollEnabled(): bool
    {
        return $this->isEnabled() && (bool) $this->value('hr.payroll.enabled');
    }

    /**
     * Employee code prefix.
     *
     * @return string
     */
    public function employeeCodePrefix(): string
    {
        $prefix = strtoupper(trim((string) $this->value('hr.employee_code_prefix')));

        return $prefix !== '' ? $prefix : 'EMP';
    }

    /**
     * Default employment status.
     *
     * @return EmploymentStatus
     */
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

    /**
     * Is working date.
     *
     * @param  Carbon  $date
     * @return bool
     */
    public function isWorkingDate(Carbon $date): bool
    {
        return in_array($date->isoWeekday(), $this->workingDays(), true);
    }

    /**
     * Work start time.
     *
     * @return string
     */
    public function workStartTime(): string
    {
        $time = (string) $this->value('hr.work_start_time');

        return preg_match('/^\d{2}:\d{2}$/', $time) === 1 ? $time : '09:00';
    }

    /**
     * Late tolerance minutes.
     *
     * @return int
     */
    public function lateToleranceMinutes(): int
    {
        return max(0, (int) $this->value('hr.late_tolerance_minutes'));
    }

    /**
     * Leave approval required.
     *
     * @return bool
     */
    public function leaveApprovalRequired(): bool
    {
        return (bool) $this->value('hr.leave.approval_required');
    }

    /**
     * Max consecutive leave days.
     *
     * @return int
     */
    public function maxConsecutiveLeaveDays(): int
    {
        return max(0, (int) $this->value('hr.leave.max_consecutive_days'));
    }

    /**
     * Leave year start month.
     *
     * @return int
     */
    public function leaveYearStartMonth(): int
    {
        $month = (int) $this->value('hr.leave.year_start_month');

        return ($month >= 1 && $month <= 12) ? $month : 1;
    }

    /**
     * Leave year for date.
     *
     * @param  Carbon  $date
     * @return int
     */
    public function leaveYearForDate(Carbon $date): int
    {
        $startMonth = $this->leaveYearStartMonth();

        return $date->month < $startMonth ? $date->year - 1 : $date->year;
    }

    /**
     * Payroll frequency.
     *
     * @return PayFrequency
     */
    public function payrollFrequency(): PayFrequency
    {
        $value = (string) $this->value('hr.payroll.frequency');

        return PayFrequency::tryFrom($value) ?? PayFrequency::Monthly;
    }

    /**
     * Payroll currency.
     *
     * @return string
     */
    public function payrollCurrency(): string
    {
        $currency = strtoupper(trim((string) $this->value('hr.payroll.currency')));

        return strlen($currency) === 3 ? $currency : 'NGN';
    }

    /**
     * Payroll approval required.
     *
     * @return bool
     */
    public function payrollApprovalRequired(): bool
    {
        return (bool) $this->value('hr.payroll.approval_required');
    }

    /**
     * Payroll payment day.
     *
     * @return int
     */
    public function payrollPaymentDay(): int
    {
        $day = (int) $this->value('hr.payroll.payment_day');

        return ($day >= 1 && $day <= 28) ? $day : 25;
    }

    /**
     * Payroll expense account id.
     *
     * @return ?int
     */
    public function payrollExpenseAccountId(): ?int
    {
        $id = $this->value('hr.payroll.expense_account_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Payroll payable account id.
     *
     * @return ?int
     */
    public function payrollPayableAccountId(): ?int
    {
        $id = $this->value('hr.payroll.payable_account_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Payroll tax payable account id.
     *
     * @return ?int
     */
    public function payrollTaxPayableAccountId(): ?int
    {
        $id = $this->value('hr.payroll.tax_payable_account_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Payroll deduction payable account id.
     *
     * @return ?int
     */
    public function payrollDeductionPayableAccountId(): ?int
    {
        $id = $this->value('hr.payroll.deduction_payable_account_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Is payroll tax enabled.
     *
     * @return bool
     */
    public function isPayrollTaxEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.tax_enabled');
    }

    /**
     * Payroll tax table id.
     *
     * @return ?int
     */
    public function payrollTaxTableId(): ?int
    {
        $id = $this->value('hr.payroll.tax_table_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Is payroll tax ytd enabled.
     *
     * @return bool
     */
    public function isPayrollTaxYtdEnabled(): bool
    {
        return $this->isPayrollTaxEnabled() && (bool) $this->value('hr.payroll.tax_ytd_enabled');
    }

    /**
     * Payroll tax year start month.
     *
     * @return int
     */
    public function payrollTaxYearStartMonth(): int
    {
        $month = (int) $this->value('hr.payroll.tax_year_start_month');

        return ($month >= 1 && $month <= 12) ? $month : 1;
    }

    /**
     * Is pension enabled.
     *
     * @return bool
     */
    public function isPensionEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.pension_enabled');
    }

    /**
     * Pension employee percent.
     *
     * @return string
     */
    public function pensionEmployeePercent(): string
    {
        return $this->percentSetting('hr.payroll.pension_employee_percent', '8.00');
    }

    /**
     * Pension employer percent.
     *
     * @return string
     */
    public function pensionEmployerPercent(): string
    {
        return $this->percentSetting('hr.payroll.pension_employer_percent', '10.00');
    }

    /**
     * Is nhf enabled.
     *
     * @return bool
     */
    public function isNhfEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.nhf_enabled');
    }

    /**
     * Nhf percent.
     *
     * @return string
     */
    public function nhfPercent(): string
    {
        return $this->percentSetting('hr.payroll.nhf_percent', '2.50');
    }

    /**
     * Is nsitf enabled.
     *
     * @return bool
     */
    public function isNsitfEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.nsitf_enabled');
    }

    /**
     * Nsitf percent.
     *
     * @return string
     */
    public function nsitfPercent(): string
    {
        return $this->percentSetting('hr.payroll.nsitf_percent', '1.00');
    }

    /**
     * Is nibss enabled.
     *
     * @return bool
     */
    public function isNibssEnabled(): bool
    {
        return $this->isPayrollEnabled() && (bool) $this->value('hr.payroll.nibss.enabled');
    }

    /**
     * Nibss base url.
     *
     * @return ?string
     */
    public function nibssBaseUrl(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.base_url');
    }

    /**
     * Nibss api key.
     *
     * @return ?string
     */
    public function nibssApiKey(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.api_key');
    }

    /**
     * Nibss institution code.
     *
     * @return ?string
     */
    public function nibssInstitutionCode(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.institution_code');
    }

    /**
     * Nibss originator account.
     *
     * @return ?string
     */
    public function nibssOriginatorAccount(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.originator_account');
    }

    /**
     * Nibss originator bank code.
     *
     * @return ?string
     */
    public function nibssOriginatorBankCode(): ?string
    {
        return $this->nullableString('hr.payroll.nibss.originator_bank_code');
    }

    /**
     * Is overtime enabled.
     *
     * @return bool
     */
    public function isOvertimeEnabled(): bool
    {
        return $this->isAttendanceEnabled() && (bool) $this->value('hr.overtime.enabled');
    }

    /**
     * Overtime rate percent.
     *
     * @return int
     */
    public function overtimeRatePercent(): int
    {
        $rate = (int) $this->value('hr.overtime.rate_percent');

        return $rate > 0 ? $rate : 150;
    }

    /**
     * Is weekly overtime enabled.
     *
     * @return bool
     */
    public function isWeeklyOvertimeEnabled(): bool
    {
        return $this->isOvertimeEnabled() && (bool) $this->value('hr.overtime.weekly_enabled');
    }

    /**
     * Weekly overtime threshold minutes.
     *
     * @return int
     */
    public function weeklyOvertimeThresholdMinutes(): int
    {
        return max(0, (int) $this->value('hr.overtime.weekly_threshold_minutes'));
    }

    /**
     * Weekly overtime rate percent.
     *
     * @return int
     */
    public function weeklyOvertimeRatePercent(): int
    {
        $rate = (int) $this->value('hr.overtime.weekly_rate_percent');

        return $rate > 0 ? $rate : 150;
    }

    /**
     * Gps required.
     *
     * @return bool
     */
    public function gpsRequired(): bool
    {
        return $this->isAttendanceEnabled() && (bool) $this->value('hr.attendance.gps_required');
    }

    /**
     * Geofence latitude.
     *
     * @return ?float
     */
    public function geofenceLatitude(): ?float
    {
        $value = $this->value('hr.attendance.geofence_latitude');

        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * Geofence longitude.
     *
     * @return ?float
     */
    public function geofenceLongitude(): ?float
    {
        $value = $this->value('hr.attendance.geofence_longitude');

        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * Geofence radius meters.
     *
     * @return int
     */
    public function geofenceRadiusMeters(): int
    {
        return max(0, (int) $this->value('hr.attendance.geofence_radius_meters'));
    }

    /**
     * Biometric required.
     *
     * @return bool
     */
    public function biometricRequired(): bool
    {
        return $this->isAttendanceEnabled() && (bool) $this->value('hr.attendance.biometric_required');
    }

    /**
     * Is recruitment enabled.
     *
     * @return bool
     */
    public function isRecruitmentEnabled(): bool
    {
        return $this->isEnabled()
            && (bool) $this->value('hr.recruitment.enabled')
            && $this->planAllowsRecruitment();
    }

    /**
     * Is public job listings enabled.
     *
     * @return bool
     */
    public function isPublicJobListingsEnabled(): bool
    {
        return $this->isRecruitmentEnabled() && (bool) $this->value('hr.recruitment.public_listings_enabled');
    }

    /**
     * Is public job applications enabled.
     *
     * @return bool
     */
    public function isPublicJobApplicationsEnabled(): bool
    {
        return $this->isPublicJobListingsEnabled() && (bool) $this->value('hr.recruitment.public_applications_enabled');
    }

    /**
     * Offer approval required.
     *
     * @return bool
     */
    public function offerApprovalRequired(): bool
    {
        return (bool) $this->value('hr.recruitment.offer_approval_required');
    }

    /**
     * Interview required before offer.
     *
     * @return bool
     */
    public function interviewRequiredBeforeOffer(): bool
    {
        return (bool) $this->value('hr.recruitment.interview_required_before_offer');
    }

    /**
     * Default hire role.
     *
     * @return string
     */
    public function defaultHireRole(): string
    {
        $role = trim((string) $this->value('hr.recruitment.default_hire_role'));

        return $role !== '' ? $role : 'employee';
    }

    /**
     * Notify recruitment.
     *
     * @return bool
     */
    public function notifyRecruitment(): bool
    {
        return (bool) $this->value('hr.notifications.recruitment');
    }

    /**
     * Online interviews enabled.
     *
     * @return bool
     */
    public function onlineInterviewsEnabled(): bool
    {
        return $this->isRecruitmentEnabled() && (bool) $this->value('hr.interviews.online_enabled');
    }

    /**
     * Default interview meeting provider.
     *
     * @return string
     */
    public function defaultInterviewMeetingProvider(): string
    {
        $provider = strtolower(trim((string) $this->value('hr.interviews.default_provider')));

        return $provider !== '' ? $provider : 'manual';
    }

    /**
     * Auto create interview meeting.
     *
     * @return bool
     */
    public function autoCreateInterviewMeeting(): bool
    {
        return (bool) $this->value('hr.interviews.auto_create_meeting');
    }

    /**
     * Auto sync interview meeting.
     *
     * @return bool
     */
    public function autoSyncInterviewMeeting(): bool
    {
        return (bool) $this->value('hr.interviews.auto_sync_meeting');
    }

    /**
     * Cancel external interview meeting.
     *
     * @return bool
     */
    public function cancelExternalInterviewMeeting(): bool
    {
        return (bool) $this->value('hr.interviews.cancel_external_meeting');
    }

    /**
     * Default interview duration minutes.
     *
     * @return int
     */
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

    /**
     * Interview timezone.
     *
     * @return string
     */
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

    /**
     * Is performance enabled.
     *
     * @return bool
     */
    public function isPerformanceEnabled(): bool
    {
        return $this->isEnabled() && (bool) $this->value('hr.performance.enabled');
    }

    /**
     * Working hours per day.
     *
     * @return int
     */
    public function workingHoursPerDay(): int
    {
        $hours = (int) $this->value('hr.working_hours_per_day');

        return ($hours >= 1 && $hours <= 24) ? $hours : 8;
    }

    /**
     * Leave carry over enabled.
     *
     * @return bool
     */
    public function leaveCarryOverEnabled(): bool
    {
        return $this->isLeaveEnabled() && (bool) $this->value('hr.leave.carry_over_enabled');
    }

    /**
     * Leave carry over max days.
     *
     * @return int
     */
    public function leaveCarryOverMaxDays(): int
    {
        return max(0, (int) $this->value('hr.leave.carry_over_max_days'));
    }

    /**
     * Notify leave.
     *
     * @return bool
     */
    public function notifyLeave(): bool
    {
        return (bool) $this->value('hr.notifications.leave');
    }

    /**
     * Notify payroll.
     *
     * @return bool
     */
    public function notifyPayroll(): bool
    {
        return (bool) $this->value('hr.notifications.payroll');
    }

    /**
     * Assert module enabled.
     *
     * @return void
     *
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
     * Assert attendance enabled.
     *
     * @return void
     *
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
     * Assert leave enabled.
     *
     * @return void
     *
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
     * Assert payroll enabled.
     *
     * @return void
     *
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
     * Assert recruitment enabled.
     *
     * @return void
     *
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
     * Assert public job listings enabled.
     *
     * @return void
     *
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
     * Assert public job applications enabled.
     *
     * @return void
     *
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
     * Assert performance enabled.
     *
     * @return void
     *
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

    /**
     * Percent setting.
     *
     * @param  string  $key
     * @param  string  $fallback
     * @return string
     */
    protected function percentSetting(string $key, string $fallback): string
    {
        $value = $this->value($key);

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $fallback;
        }

        $normalized = number_format((float) $value, 2, '.', '');

        return bccomp($normalized, '0', 2) < 0 ? $fallback : $normalized;
    }

    /**
     * Nullable string.
     *
     * @param  string  $key
     * @return ?string
     */
    protected function nullableString(string $key): ?string
    {
        $value = trim((string) ($this->value($key) ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Plan allows recruitment.
     *
     * @return bool
     */
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

    /**
     * Value.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function value(string $key): mixed
    {
        if ($this->settings === []) {
            $this->settings = $this->all();
        }

        return $this->settings[$key] ?? null;
    }
}
