<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType as LeaveTypeCode;
use App\Http\Middleware\EnsureHrEnabled;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\HrSummaryService;
use App\Services\Tenant\HR\LeaveRequestService;
use App\Services\Tenant\HR\LeaveTypeService;
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

    expect($summary)->toHaveKeys(['employees', 'departments', 'attendance_today', 'leave', 'payroll'])
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
