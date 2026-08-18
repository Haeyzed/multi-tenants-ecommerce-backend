<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType as LeaveTypeCode;
use App\Http\Middleware\EnsureHrEnabled;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveBalance;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\HrReportService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\HrSummaryService;
use App\Services\Tenant\HR\LeaveRequestService;
use App\Services\Tenant\HR\LeaveTypeService;
use App\Services\Tenant\HR\TaxTableService;
use App\Services\Tenant\Settings\TenantSettingsService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrations = [
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_16_161019_create_departments_table.php',
        '2026_08_16_161032_create_employees_table.php',
        '2026_08_17_211947_create_designations_table.php',
        '2026_08_17_211951_add_designation_id_to_employees_table.php',
        '2026_08_17_211954_create_attendances_table.php',
        '2026_08_17_212000_create_leave_requests_table.php',
        '2026_08_18_003141_create_leave_types_table.php',
        '2026_08_18_003144_create_leave_balances_table.php',
        '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php',
        '2026_08_18_003148_add_manager_id_to_departments_table.php',
        '2026_08_18_001429_create_payroll_runs_table.php',
        '2026_08_18_001431_create_payroll_items_table.php',
        '2026_08_18_001434_create_payroll_item_lines_table.php',
        '2026_08_18_014813_create_payroll_periods_table.php',
        '2026_08_18_014825_add_leave_carry_over_and_overtime_columns.php',
        '2026_08_18_134108_create_work_schedules_table.php',
        '2026_08_18_134110_create_work_schedule_days_table.php',
        '2026_08_18_134113_create_overtime_policies_table.php',
        '2026_08_18_134116_create_public_holidays_table.php',
        '2026_08_18_134118_create_tax_tables_table.php',
        '2026_08_18_134122_create_tax_table_bands_table.php',
        '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php',
        '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php',
        '2026_08_18_145943_create_job_openings_table.php',
        '2026_08_18_145947_create_performance_cycles_table.php',
    ];

    foreach ($migrations as $file) {
        $table = match ($file) {
            '2026_08_15_060001_create_commerce_settings_table.php' => 'commerce_settings',
            '2026_08_16_161019_create_departments_table.php' => 'departments',
            '2026_08_16_161032_create_employees_table.php' => 'employees',
            '2026_08_17_211947_create_designations_table.php' => 'designations',
            '2026_08_17_211951_add_designation_id_to_employees_table.php' => null,
            '2026_08_17_211954_create_attendances_table.php' => 'attendances',
            '2026_08_17_212000_create_leave_requests_table.php' => 'leave_requests',
            '2026_08_18_003141_create_leave_types_table.php' => 'leave_types',
            '2026_08_18_003144_create_leave_balances_table.php' => 'leave_balances',
            '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php' => null,
            '2026_08_18_003148_add_manager_id_to_departments_table.php' => null,
            '2026_08_18_001429_create_payroll_runs_table.php' => 'payroll_runs',
            '2026_08_18_001431_create_payroll_items_table.php' => 'payroll_items',
            '2026_08_18_001434_create_payroll_item_lines_table.php' => 'payroll_item_lines',
            '2026_08_18_014813_create_payroll_periods_table.php' => 'payroll_periods',
            '2026_08_18_014825_add_leave_carry_over_and_overtime_columns.php' => null,
            '2026_08_18_134108_create_work_schedules_table.php' => 'work_schedules',
            '2026_08_18_134110_create_work_schedule_days_table.php' => 'work_schedule_days',
            '2026_08_18_134113_create_overtime_policies_table.php' => 'overtime_policies',
            '2026_08_18_134116_create_public_holidays_table.php' => 'public_holidays',
            '2026_08_18_134118_create_tax_tables_table.php' => 'tax_tables',
            '2026_08_18_134122_create_tax_table_bands_table.php' => 'tax_table_bands',
            '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php' => null,
            '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php' => null,
            '2026_08_18_145943_create_job_openings_table.php' => 'job_openings',
            '2026_08_18_145947_create_performance_cycles_table.php' => 'performance_cycles',
            default => null,
        };

        if ($table !== null && Schema::hasTable($table)) {
            continue;
        }

        if ($file === '2026_08_17_211951_add_designation_id_to_employees_table.php' && Schema::hasColumn('employees', 'designation_id')) {
            continue;
        }

        if ($file === '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php' && Schema::hasColumn('employees', 'manager_id')) {
            continue;
        }

        if ($file === '2026_08_18_003148_add_manager_id_to_departments_table.php' && Schema::hasColumn('departments', 'manager_id')) {
            continue;
        }

        if ($file === '2026_08_18_014825_add_leave_carry_over_and_overtime_columns.php' && Schema::hasColumn('attendances', 'overtime_minutes')) {
            continue;
        }

        if ($file === '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php' && Schema::hasColumn('employees', 'work_schedule_id')) {
            continue;
        }

        if ($file === '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php' && Schema::hasColumn('overtime_policies', 'weekly_threshold_minutes')) {
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

test('hr settings return defaults and persist allowlisted keys', function (): void {
    $settings = app(HrSettingsService::class);

    $defaults = $settings->all();

    expect($defaults['hr.enabled'])->toBeTrue()
        ->and($defaults['hr.employee_code_prefix'])->toBe('EMP')
        ->and($defaults['hr.payroll.frequency'])->toBe('monthly')
        ->and($defaults['hr.payroll.approval_required'])->toBeFalse()
        ->and($defaults['hr.payroll.tax_enabled'])->toBeFalse()
        ->and($defaults['hr.overtime.enabled'])->toBeFalse()
        ->and($defaults['hr.leave.carry_over_enabled'])->toBeFalse()
        ->and($settings->workingDays())->toBe([1, 2, 3, 4, 5]);

    $updated = $settings->update([
        'hr.employee_code_prefix' => 'STAFF',
        'hr.working_days' => '1,2,3,4,5,6',
        'hr.payroll.currency' => 'usd',
        'hr.leave.approval_required' => false,
    ]);

    expect($updated['hr.employee_code_prefix'])->toBe('STAFF')
        ->and(app(HrSettingsService::class)->payrollCurrency())->toBe('USD')
        ->and(app(HrSettingsService::class)->leaveApprovalRequired())->toBeFalse()
        ->and(app(HrSettingsService::class)->workingDays())->toContain(6);
});

test('generic tenant settings do not expose the hr domain', function (): void {
    $settings = app(TenantSettingsService::class);

    expect($settings->domains())->not->toContain('hr');
    expect(fn () => $settings->getDomain('hr'))->toThrow(ValidationException::class);
});

test('hr enabled middleware blocks when the tenant setting is off', function (): void {
    app(HrSettingsService::class)->update(['hr.enabled' => false]);

    $response = app(EnsureHrEnabled::class)->handle(
        Request::create('/api/employees', 'GET'),
        fn () => response()->json(['ok' => true]),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

test('leave types are seeded and remain tenant configurable', function (): void {
    $service = app(LeaveTypeService::class);
    $types = $service->list();

    expect($types->total())->toBeGreaterThanOrEqual(4)
        ->and($service->options()->pluck('value'))->toContain(LeaveTypeCode::Annual->value);

    $custom = $service->store([
        'name' => 'Study',
        'code' => 'study',
        'is_paid' => true,
        'default_days' => 5,
    ]);

    expect($custom->code)->toBe('study');
});

test('leave auto-approves when approval is disabled and tracks balance', function (): void {
    Event::fake();

    app(HrSettingsService::class)->update([
        'hr.leave.approval_required' => false,
        'hr.working_days' => '1,2,3,4,5,6,7',
    ]);

    $employee = Employee::factory()->create();
    $service = app(LeaveRequestService::class);

    $leave = $service->store([
        'employee_id' => $employee->id,
        'type' => LeaveTypeCode::Annual->value,
        'start_date' => now()->next('Monday')->toDateString(),
        'end_date' => now()->next('Monday')->toDateString(),
    ]);

    expect($leave->status)->toBe(LeaveStatus::Approved);

    $balances = $service->balancesFor($employee);
    $annual = collect($balances)->first(fn ($balance) => $balance->leaveType?->code === LeaveTypeCode::Annual->value);

    expect($annual)->not->toBeNull()
        ->and($annual->used)->toBeGreaterThanOrEqual(1)
        ->and($annual->remaining())->toBeLessThan($annual->entitled);
});

test('employees can view their own profile but not another employee', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $owner->id]);
    $otherEmployee = Employee::factory()->create(['user_id' => $other->id]);

    expect($owner->can('view', $employee))->toBeTrue()
        ->and($owner->can('view', $otherEmployee))->toBeFalse()
        ->and($owner->can('viewSalary', $employee))->toBeTrue()
        ->and($other->can('viewSalary', $employee))->toBeFalse();
});

test('hr summary returns foundation totals', function (): void {
    Employee::factory()->create();

    $summary = app(HrSummaryService::class)->summary();

    expect($summary)->toHaveKeys(['employees', 'departments', 'attendance_today', 'overtime_minutes_this_period', 'leave', 'payroll'])
        ->and($summary['payroll'])->toHaveKeys(['draft', 'pending_approval', 'processed', 'paid', 'current_period', 'open_periods'])
        ->and($summary['employees']['total'])->toBeGreaterThanOrEqual(1);
});

test('hr settings permissions are limited to authorized roles', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    expect($admin->can('hr.settings.view'))->toBeTrue()
        ->and($admin->can('hr.settings.update'))->toBeTrue()
        ->and($customer->can('hr.settings.update'))->toBeFalse();
});

test('hr operational routes require the hr feature and enabled setting', function (): void {
    $employees = app('router')->getRoutes()->getByName('tenant.employees.index');
    $settings = app('router')->getRoutes()->getByName('tenant.hr.settings.show');

    expect($employees)->not->toBeNull()
        ->and(implode(',', $employees->gatherMiddleware()))->toContain('feature:hr')
        ->and(implode(',', $employees->gatherMiddleware()))->toContain('hr.enabled')
        ->and($settings)->not->toBeNull()
        ->and(implode(',', $settings->gatherMiddleware()))->toContain('feature:hr')
        ->and(implode(',', $settings->gatherMiddleware()))->not->toContain('hr.enabled');
});

test('leave carry-over uses the previous year remaining days up to the configured max', function (): void {
    app(LeaveTypeService::class)->ensureDefaults();
    app(HrSettingsService::class)->update([
        'hr.leave.carry_over_enabled' => true,
        'hr.leave.carry_over_max_days' => 5,
        'hr.leave.year_start_month' => 1,
    ]);

    $employee = Employee::factory()->create();
    $annual = LeaveType::query()->where('code', LeaveTypeCode::Annual->value)->first();

    expect($annual)->not->toBeNull()
        ->and($annual->allow_carry_over)->toBeTrue();

    LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $annual->id,
        'year' => now()->year - 1,
        'entitled' => 21,
        'carried_over' => 0,
        'used' => 10,
    ]);

    $balances = app(LeaveRequestService::class)->balancesFor($employee, now()->year);
    $current = collect($balances)->first(fn ($balance) => $balance->leave_type_id === $annual->id);

    expect($current)->not->toBeNull()
        ->and($current->carried_over)->toBe(5)
        ->and($current->remaining())->toBe(26);
});

test('tax table list seeds nigeria paye when none exist', function (): void {
    $tables = app(TaxTableService::class)->list();

    expect($tables->total())->toBe(1)
        ->and($tables->first()?->country_code)->toBe('NG')
        ->and($tables->first()?->bands)->not->toBeEmpty();
});

test('hr reports return attendance leave overtime and headcount windows', function (): void {
    $employee = Employee::factory()->create();

    $attendance = app(HrReportService::class)->attendance([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->endOfMonth()->toDateString(),
        'employee_id' => $employee->id,
    ]);
    $leave = app(HrReportService::class)->leave([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->endOfMonth()->toDateString(),
    ]);
    $overtime = app(HrReportService::class)->overtime([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->endOfMonth()->toDateString(),
    ]);
    $payroll = app(HrReportService::class)->payroll([
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->endOfMonth()->toDateString(),
    ]);
    $headcount = app(HrReportService::class)->headcount([
        'as_of' => now()->toDateString(),
    ]);

    expect($attendance)->toHaveKeys(['from', 'to', 'totals', 'rows'])
        ->and($leave)->toHaveKeys(['from', 'to', 'by_type', 'rows'])
        ->and($overtime)->toHaveKeys(['from', 'to', 'totals', 'rows'])
        ->and($payroll)->toHaveKeys(['from', 'to', 'totals', 'by_department', 'rows'])
        ->and($headcount['total'])->toBeGreaterThanOrEqual(1);
});

test('hr report and workforce routes are registered behind the hr feature', function (): void {
    $attendance = app('router')->getRoutes()->getByName('tenant.hr.reports.attendance');
    $schedules = app('router')->getRoutes()->getByName('tenant.work-schedules.index');
    $policies = app('router')->getRoutes()->getByName('tenant.overtime-policies.index');
    $holidays = app('router')->getRoutes()->getByName('tenant.public-holidays.index');
    $taxes = app('router')->getRoutes()->getByName('tenant.tax-tables.index');
    $pdf = app('router')->getRoutes()->getByName('tenant.payroll-runs.items.pdf');
    $register = app('router')->getRoutes()->getByName('tenant.payroll-runs.payment-register');
    $history = app('router')->getRoutes()->getByName('tenant.employees.salary.revisions');
    $jobs = app('router')->getRoutes()->getByName('tenant.job-openings.index');
    $reviews = app('router')->getRoutes()->getByName('tenant.performance-reviews.index');
    $statutory = app('router')->getRoutes()->getByName('tenant.hr.reports.statutory');
    $nibss = app('router')->getRoutes()->getByName('tenant.payroll-runs.nibss.submit');

    expect($attendance)->not->toBeNull()
        ->and(implode(',', $attendance->gatherMiddleware()))->toContain('feature:hr')
        ->and(implode(',', $attendance->gatherMiddleware()))->toContain('hr.enabled')
        ->and($schedules)->not->toBeNull()
        ->and($policies)->not->toBeNull()
        ->and($holidays)->not->toBeNull()
        ->and($taxes)->not->toBeNull()
        ->and($pdf)->not->toBeNull()
        ->and($register)->not->toBeNull()
        ->and($history)->not->toBeNull()
        ->and($jobs)->not->toBeNull()
        ->and($reviews)->not->toBeNull()
        ->and($statutory)->not->toBeNull()
        ->and($nibss)->not->toBeNull();
});

test('managers can view hr reports while customers cannot', function (): void {
    $manager = User::factory()->create();
    $manager->syncRoles(['manager']);

    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    expect($manager->can('hr.reports.view'))->toBeTrue()
        ->and($manager->can('viewHrReports'))->toBeTrue()
        ->and($customer->can('hr.reports.view'))->toBeFalse()
        ->and($customer->can('viewHrReports'))->toBeFalse();
});
