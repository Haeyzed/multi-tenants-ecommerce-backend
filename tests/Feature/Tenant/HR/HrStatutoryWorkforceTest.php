<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Enums\Tenant\HR\PerformanceReviewStatus;
use App\Events\JobApplicationReceived;
use App\Events\PayrollPaid;
use App\Events\PayrollProcessed;
use App\Events\PayslipAvailable;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeSalary;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\JobOpening;
use App\Models\Tenant\PerformanceCycle;
use App\Models\Tenant\PerformanceReview;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\AttendanceService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\JobApplicationService;
use App\Services\Tenant\HR\JobOpeningService;
use App\Services\Tenant\HR\OvertimePolicyService;
use App\Services\Tenant\HR\PayrollRunService;
use App\Services\Tenant\HR\PerformanceCycleService;
use App\Services\Tenant\HR\PerformanceReviewService;
use App\Services\Tenant\HR\StatutoryReturnService;
use App\Services\Tenant\HR\TaxTableService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrations = [
        '2026_08_16_161019_create_departments_table.php',
        '2026_08_16_161032_create_employees_table.php',
        '2026_08_17_211947_create_designations_table.php',
        '2026_08_17_211951_add_designation_id_to_employees_table.php',
        '2026_08_17_211954_create_attendances_table.php',
        '2026_08_17_212000_create_leave_requests_table.php',
        '2026_08_18_003141_create_leave_types_table.php',
        '2026_08_18_003144_create_leave_balances_table.php',
        '2026_08_18_001426_create_employee_salaries_table.php',
        '2026_08_18_014811_create_employee_salary_components_table.php',
        '2026_08_18_001429_create_payroll_runs_table.php',
        '2026_08_18_001431_create_payroll_items_table.php',
        '2026_08_18_001434_create_payroll_item_lines_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_18_014813_create_payroll_periods_table.php',
        '2026_08_18_014816_add_payroll_period_id_to_payroll_runs_table.php',
        '2026_08_18_014825_add_leave_carry_over_and_overtime_columns.php',
        '2026_08_18_134108_create_work_schedules_table.php',
        '2026_08_18_134110_create_work_schedule_days_table.php',
        '2026_08_18_134113_create_overtime_policies_table.php',
        '2026_08_18_134116_create_public_holidays_table.php',
        '2026_08_18_134118_create_tax_tables_table.php',
        '2026_08_18_134122_create_tax_table_bands_table.php',
        '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php',
        '2026_08_18_142459_add_bank_and_tax_columns_to_employees_table.php',
        '2026_08_18_142501_add_payslip_snapshot_columns_to_payroll_items_table.php',
        '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php',
        '2026_08_18_145939_add_clock_source_and_location_to_attendances_table.php',
        '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php',
        '2026_08_18_145943_create_job_openings_table.php',
        '2026_08_18_145945_create_job_applications_table.php',
        '2026_08_18_145947_create_performance_cycles_table.php',
        '2026_08_18_145949_create_performance_reviews_table.php',
        '2026_08_18_145952_add_nibss_disbursement_columns_to_payroll_runs_table.php',
        '2026_08_18_154933_create_candidates_table.php',
        '2026_08_18_154935_create_recruitment_stages_table.php',
        '2026_08_18_154937_add_ats_columns_to_job_openings_table.php',
        '2026_08_18_154939_add_candidate_and_stage_to_job_applications_table.php',
        '2026_08_18_154943_create_application_stage_histories_table.php',
    ];

    foreach ($migrations as $file) {
        $table = match ($file) {
            '2026_08_16_161019_create_departments_table.php' => 'departments',
            '2026_08_16_161032_create_employees_table.php' => 'employees',
            '2026_08_17_211947_create_designations_table.php' => 'designations',
            '2026_08_17_211954_create_attendances_table.php' => 'attendances',
            '2026_08_17_212000_create_leave_requests_table.php' => 'leave_requests',
            '2026_08_18_003141_create_leave_types_table.php' => 'leave_types',
            '2026_08_18_003144_create_leave_balances_table.php' => 'leave_balances',
            '2026_08_18_001426_create_employee_salaries_table.php' => 'employee_salaries',
            '2026_08_18_014811_create_employee_salary_components_table.php' => 'employee_salary_components',
            '2026_08_18_001429_create_payroll_runs_table.php' => 'payroll_runs',
            '2026_08_18_001431_create_payroll_items_table.php' => 'payroll_items',
            '2026_08_18_001434_create_payroll_item_lines_table.php' => 'payroll_item_lines',
            '2026_08_15_060001_create_commerce_settings_table.php' => 'commerce_settings',
            '2026_08_18_014813_create_payroll_periods_table.php' => 'payroll_periods',
            '2026_08_18_134108_create_work_schedules_table.php' => 'work_schedules',
            '2026_08_18_134110_create_work_schedule_days_table.php' => 'work_schedule_days',
            '2026_08_18_134113_create_overtime_policies_table.php' => 'overtime_policies',
            '2026_08_18_134116_create_public_holidays_table.php' => 'public_holidays',
            '2026_08_18_134118_create_tax_tables_table.php' => 'tax_tables',
            '2026_08_18_134122_create_tax_table_bands_table.php' => 'tax_table_bands',
            '2026_08_18_145943_create_job_openings_table.php' => 'job_openings',
            '2026_08_18_145945_create_job_applications_table.php' => 'job_applications',
            '2026_08_18_145947_create_performance_cycles_table.php' => 'performance_cycles',
            '2026_08_18_145949_create_performance_reviews_table.php' => 'performance_reviews',
            '2026_08_18_154933_create_candidates_table.php' => 'candidates',
            '2026_08_18_154935_create_recruitment_stages_table.php' => 'recruitment_stages',
            '2026_08_18_154943_create_application_stage_histories_table.php' => 'application_stage_histories',
            default => null,
        };

        if ($table !== null && Schema::hasTable($table)) {
            continue;
        }

        if ($file === '2026_08_17_211951_add_designation_id_to_employees_table.php' && Schema::hasColumn('employees', 'designation_id')) {
            continue;
        }

        if ($file === '2026_08_18_014816_add_payroll_period_id_to_payroll_runs_table.php' && Schema::hasColumn('payroll_runs', 'payroll_period_id')) {
            continue;
        }

        if ($file === '2026_08_18_014825_add_leave_carry_over_and_overtime_columns.php' && Schema::hasColumn('attendances', 'overtime_minutes')) {
            continue;
        }

        if ($file === '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php' && Schema::hasColumn('employees', 'work_schedule_id')) {
            continue;
        }

        if ($file === '2026_08_18_142459_add_bank_and_tax_columns_to_employees_table.php' && Schema::hasColumn('employees', 'bank_name')) {
            continue;
        }

        if ($file === '2026_08_18_142501_add_payslip_snapshot_columns_to_payroll_items_table.php' && Schema::hasColumn('payroll_items', 'scheduled_days')) {
            continue;
        }

        if ($file === '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php' && Schema::hasColumn('overtime_policies', 'weekly_threshold_minutes')) {
            continue;
        }

        if ($file === '2026_08_18_145939_add_clock_source_and_location_to_attendances_table.php' && Schema::hasColumn('attendances', 'clock_source')) {
            continue;
        }

        if ($file === '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php' && Schema::hasColumn('payroll_items', 'ytd_gross')) {
            continue;
        }

        if ($file === '2026_08_18_145952_add_nibss_disbursement_columns_to_payroll_runs_table.php' && Schema::hasColumn('payroll_runs', 'nibss_reference')) {
            continue;
        }

        if ($file === '2026_08_18_154937_add_ats_columns_to_job_openings_table.php' && Schema::hasColumn('job_openings', 'slug')) {
            continue;
        }

        if ($file === '2026_08_18_154939_add_candidate_and_stage_to_job_applications_table.php' && Schema::hasColumn('job_applications', 'candidate_id')) {
            continue;
        }

        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);
});

test('clock-in requires gps when the workplace geofence is enabled', function (): void {
    app(HrSettingsService::class)->update([
        'hr.attendance.gps_required' => true,
        'hr.attendance.geofence_latitude' => '6.5244',
        'hr.attendance.geofence_longitude' => '3.3792',
        'hr.attendance.geofence_radius_meters' => 200,
    ]);

    $employee = Employee::factory()->create();
    $service = app(AttendanceService::class);

    expect(fn () => $service->clockIn($employee))->toThrow(ValidationException::class);

    $attendance = $service->clockIn($employee, [
        'latitude' => 6.5244,
        'longitude' => 3.3792,
    ]);

    expect($attendance->clock_source->value)->toBe('gps')
        ->and((float) $attendance->latitude)->toBe(6.5244);

    expect(fn () => $service->clockOut($employee, [
        'latitude' => 6.6,
        'longitude' => 3.5,
    ]))->toThrow(ValidationException::class);
});

test('pension and nhf are deducted from net and shown on statutory returns', function (): void {
    app(HrSettingsService::class)->update([
        'hr.payroll.pension_enabled' => true,
        'hr.payroll.nhf_enabled' => true,
        'hr.payroll.nsitf_enabled' => true,
    ]);

    $employee = Employee::factory()->create([
        'pension_pin' => 'PEN123',
        'nhf_number' => 'NHF123',
    ]);
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '100000.00',
    ]);

    $run = app(PayrollRunService::class)->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);

    $item = $run->items->first();
    $pension = $item?->lines->firstWhere('code', 'pension');
    $nhf = $item?->lines->firstWhere('code', 'nhf');

    expect($pension)->not->toBeNull()
        ->and((float) $pension->amount)->toBe(8000.0)
        ->and((float) $nhf->amount)->toBe(2500.0)
        ->and((float) $item->employer_pension)->toBe(10000.0)
        ->and((float) $item->employer_nsitf)->toBe(1000.0)
        ->and((float) $item->net_pay)->toBe(89500.0);

    $admin = User::factory()->create();
    Event::fake([PayrollProcessed::class, PayrollPaid::class, PayslipAvailable::class]);
    $processed = app(PayrollRunService::class)->process($run, $admin);

    $returns = app(StatutoryReturnService::class)->generate([
        'from' => '2026-08-01',
        'to' => '2026-08-31',
        'kind' => 'pension',
    ]);

    expect($returns['rows'])->not->toBeEmpty()
        ->and((float) $returns['totals']['pension'])->toBe(8000.0)
        ->and($processed->status)->toBe(PayrollRunStatus::Processed);
});

test('ytd paye withholds the annualized remainder after prior periods', function (): void {
    app(TaxTableService::class)->ensureDefaults();
    app(HrSettingsService::class)->update([
        'hr.payroll.tax_enabled' => true,
        'hr.payroll.tax_ytd_enabled' => true,
    ]);

    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '100000.00',
    ]);

    $first = app(PayrollRunService::class)->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);
    $second = app(PayrollRunService::class)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
    ]);

    $firstItem = $first->items->first();
    $secondItem = $second->items->first();
    $firstPaye = $firstItem?->lines->firstWhere('code', 'paye');
    $secondPaye = $secondItem?->lines->firstWhere('code', 'paye');

    expect((float) $firstPaye->amount)->toBe(6500.0)
        ->and((float) $secondItem->ytd_gross)->toBe(200000.0)
        ->and((float) $secondPaye->amount)->toBe(6500.0)
        ->and((float) $secondItem->ytd_paye)->toBe(13000.0);
});

test('weekly overtime pays hours beyond the weekly threshold', function (): void {
    app(HrSettingsService::class)->update(['hr.overtime.enabled' => true]);

    app(OvertimePolicyService::class)->store([
        'name' => 'Weekly 40',
        'is_default' => true,
        'daily_threshold_minutes' => 1440,
        'weekly_threshold_minutes' => 2400,
        'weekly_rate_percent' => 150,
    ]);

    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '100000.00',
    ]);

    foreach (['2026-08-17', '2026-08-18', '2026-08-19', '2026-08-20', '2026-08-21'] as $date) {
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'work_date' => $date,
            'checked_in_at' => $date.' 09:00:00',
            'checked_out_at' => $date.' 18:00:00',
            'overtime_minutes' => 0,
        ]);
    }

    $run = app(PayrollRunService::class)->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);

    $overtime = $run->items->first()?->lines->firstWhere('code', 'overtime');

    expect($overtime)->not->toBeNull()
        ->and((float) $overtime->amount)->toBeGreaterThan(0)
        ->and($run->items->first()->overtime_minutes)->toBe(300);
});

test('nibss processor posts bulk credits for a paid run', function (): void {
    Event::fake([PayrollProcessed::class, PayrollPaid::class, PayslipAvailable::class]);
    Http::fake([
        'https://nibss.test/credits' => Http::response(['reference' => 'NIBSS-1', 'status' => 'accepted'], 200),
    ]);

    app(HrSettingsService::class)->update([
        'hr.payroll.nibss.enabled' => true,
        'hr.payroll.nibss.base_url' => 'https://nibss.test',
        'hr.payroll.nibss.api_key' => 'secret',
        'hr.payroll.nibss.institution_code' => '999',
        'hr.payroll.nibss.originator_account' => '0001112223',
        'hr.payroll.nibss.originator_bank_code' => '058',
    ]);

    $employee = Employee::factory()->create([
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'Ada Lovelace',
    ]);
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '100000.00',
    ]);

    $admin = User::factory()->create();
    $service = app(PayrollRunService::class);
    $run = $service->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);
    $paid = $service->pay($service->process($run, $admin), $admin, ['post_to_accounting' => false]);

    expect($paid->nibss_status)->toBe('submitted')
        ->and($paid->nibss_reference)->toBe('NIBSS-1');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://nibss.test/credits');
});

test('recruitment openings accept applications only while open', function (): void {
    Event::fake([JobApplicationReceived::class]);
    $opening = app(JobOpeningService::class)->store([
        'title' => 'Store lead',
        'status' => JobOpeningStatus::Draft,
    ]);

    expect(fn () => app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]))->toThrow(ValidationException::class);

    $open = app(JobOpeningService::class)->update($opening, ['status' => JobOpeningStatus::Open]);
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $open->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);

    expect($application->status)->toBe(JobApplicationStatus::Received)
        ->and(JobOpening::query()->count())->toBe(1)
        ->and(JobApplication::query()->count())->toBe(1);
});

test('performance reviews cannot be duplicated in a cycle', function (): void {
    $employee = Employee::factory()->create();
    $cycle = app(PerformanceCycleService::class)->store([
        'name' => '2026 H1',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-06-30',
    ]);

    $review = app(PerformanceReviewService::class)->store([
        'performance_cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
        'rating' => 4.5,
        'status' => PerformanceReviewStatus::Submitted,
    ]);

    expect($review->submitted_at)->not->toBeNull()
        ->and(PerformanceCycle::query()->count())->toBe(1)
        ->and(PerformanceReview::query()->count())->toBe(1);

    expect(fn () => app(PerformanceReviewService::class)->store([
        'performance_cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
    ]))->toThrow(ValidationException::class);
});
