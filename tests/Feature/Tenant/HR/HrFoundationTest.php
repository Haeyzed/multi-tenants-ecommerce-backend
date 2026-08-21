<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Enums\Tenant\HR\EmploymentChangeType;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType;
use App\Events\EmployeeCreated;
use App\Events\LeaveRequested;
use App\Events\LeaveReviewed;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\EmploymentRecord;
use App\Models\HR\LeaveRequest;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\AttendanceService;
use App\Services\Tenant\HR\DepartmentService;
use App\Services\Tenant\HR\DesignationService;
use App\Services\Tenant\HR\EmployeeService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\LeaveRequestService;
use App\Services\Tenant\HR\LeaveTypeService;
use App\Services\Tenant\HR\OvertimePolicyService;
use App\Services\Tenant\HR\PublicHolidayService;
use App\Services\Tenant\HR\WorkScheduleService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        '2026_08_19_182044_add_leave_type_id_to_leave_requests_table.php',
        '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php',
        '2026_08_18_003148_add_manager_id_to_departments_table.php',
        '2026_08_18_014822_create_employment_records_table.php',
        '2026_08_18_014825_add_leave_carry_over_and_overtime_columns.php',
        '2026_08_18_134108_create_work_schedules_table.php',
        '2026_08_18_134110_create_work_schedule_days_table.php',
        '2026_08_18_134113_create_overtime_policies_table.php',
        '2026_08_18_134116_create_public_holidays_table.php',
        '2026_08_18_134118_create_tax_tables_table.php',
        '2026_08_18_134122_create_tax_table_bands_table.php',
        '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php',
        '2026_08_18_142459_add_bank_and_tax_columns_to_employees_table.php',
        '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php',
        '2026_08_18_145939_add_clock_source_and_location_to_attendances_table.php',
        '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php',
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
            '2026_08_19_182044_add_leave_type_id_to_leave_requests_table.php' => null,
            '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php' => null,
            '2026_08_18_003148_add_manager_id_to_departments_table.php' => null,
            '2026_08_18_014822_create_employment_records_table.php' => 'employment_records',
            '2026_08_18_014825_add_leave_carry_over_and_overtime_columns.php' => null,
            '2026_08_18_134108_create_work_schedules_table.php' => 'work_schedules',
            '2026_08_18_134110_create_work_schedule_days_table.php' => 'work_schedule_days',
            '2026_08_18_134113_create_overtime_policies_table.php' => 'overtime_policies',
            '2026_08_18_134116_create_public_holidays_table.php' => 'public_holidays',
            '2026_08_18_134118_create_tax_tables_table.php' => 'tax_tables',
            '2026_08_18_134122_create_tax_table_bands_table.php' => 'tax_table_bands',
            '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php' => null,
            '2026_08_18_142459_add_bank_and_tax_columns_to_employees_table.php' => null,
            '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php' => null,
            '2026_08_18_145939_add_clock_source_and_location_to_attendances_table.php' => null,
            '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php' => null,
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

        if ($file === '2026_08_19_182044_add_leave_type_id_to_leave_requests_table.php' && Schema::hasColumn('leave_requests', 'leave_type_id')) {
            continue;
        }

        if ($file === '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php' && Schema::hasColumn('employees', 'work_schedule_id')) {
            continue;
        }

        if ($file === '2026_08_18_142459_add_bank_and_tax_columns_to_employees_table.php' && Schema::hasColumn('employees', 'bank_name')) {
            continue;
        }

        if ($file === '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php' && Schema::hasColumn('overtime_policies', 'weekly_threshold_minutes')) {
            continue;
        }

        if ($file === '2026_08_18_145939_add_clock_source_and_location_to_attendances_table.php' && Schema::hasColumn('attendances', 'clock_source')) {
            continue;
        }

        if ($file === '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php' && Schema::hasColumn('employees', 'pension_pin')) {
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

    Storage::fake('public');
    config([
        'media-library.disk_name' => 'public',
        'media-library.queue_conversions_by_default' => false,
        'notifications.queue' => false,
    ]);
});

test('department service supports crud', function (): void {
    $service = app(DepartmentService::class);

    $department = $service->store([
        'name' => 'Engineering',
        'code' => 'ENG',
        'description' => 'Product engineering',
        'is_active' => true,
    ]);

    expect($department->name)->toBe('Engineering')
        ->and($department->code)->toBe('ENG');

    $updated = $service->update($department, ['name' => 'Platform Engineering']);
    expect($updated->name)->toBe('Platform Engineering');

    expect($service->list(['search' => 'Platform'])->total())->toBe(1)
        ->and($service->options())->not->toBeEmpty();

    $service->destroy($updated);
    expect(Department::query()->whereKey($department->id)->exists())->toBeFalse();
});

test('designation service supports crud and department scoping', function (): void {
    $department = Department::factory()->create();
    $service = app(DesignationService::class);

    $designation = $service->store([
        'department_id' => $department->id,
        'name' => 'Backend Engineer',
        'code' => 'BE',
        'is_active' => true,
    ]);

    expect($designation->name)->toBe('Backend Engineer')
        ->and($designation->department_id)->toBe($department->id);

    $updated = $service->update($designation, ['name' => 'Senior Backend Engineer']);
    expect($updated->name)->toBe('Senior Backend Engineer')
        ->and($service->options($department->id)->pluck('value'))->toContain($designation->id);

    $service->destroy($updated);
    expect(Designation::query()->whereKey($designation->id)->exists())->toBeFalse();
});

test('employee service links to an existing user once', function (): void {
    Event::fake([EmployeeCreated::class]);

    $user = User::factory()->create();
    $department = Department::factory()->create();
    $designation = Designation::factory()->forDepartment($department)->create();
    $service = app(EmployeeService::class);

    $employee = $service->store([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'designation_id' => $designation->id,
        'job_title' => 'Backend Engineer',
        'employee_number' => 'EMP-100',
        'employment_status' => EmploymentStatus::Active->value,
    ]);

    expect($employee->user_id)->toBe($user->id)
        ->and($employee->department_id)->toBe($department->id)
        ->and($employee->designation_id)->toBe($designation->id)
        ->and($employee->employment_status)->toBe(EmploymentStatus::Active)
        ->and($user->fresh()->employee)->not->toBeNull();

    Event::assertDispatched(EmployeeCreated::class);

    expect(fn () => $service->store([
        'user_id' => $user->id,
        'job_title' => 'Duplicate',
    ]))->toThrow(ValidationException::class);

    $updated = $service->update($employee, [
        'employment_status' => EmploymentStatus::OnLeave->value,
        'job_title' => 'Senior Backend Engineer',
    ]);

    expect($updated->employment_status)->toBe(EmploymentStatus::OnLeave)
        ->and($updated->job_title)->toBe('Senior Backend Engineer');

    $service->destroy($updated);
    expect(Employee::query()->whereKey($employee->id)->exists())->toBeFalse();
});

test('employee designation must match department', function (): void {
    Event::fake([EmployeeCreated::class]);

    $engineering = Department::factory()->create();
    $sales = Department::factory()->create();
    $salesTitle = Designation::factory()->forDepartment($sales)->create();

    expect(fn () => app(EmployeeService::class)->store([
        'user_id' => User::factory()->create()->id,
        'department_id' => $engineering->id,
        'designation_id' => $salesTitle->id,
    ]))->toThrow(ValidationException::class);
});

test('terminated employees cannot return to active', function (): void {
    $employee = Employee::factory()->terminated()->create();

    expect(fn () => app(EmployeeService::class)->update($employee, [
        'employment_status' => EmploymentStatus::Active->value,
    ]))->toThrow(ValidationException::class);
});

test('terminated employees cannot be assigned as managers', function (): void {
    $terminated = Employee::factory()->terminated()->create();
    $user = User::factory()->create();

    expect(fn () => app(EmployeeService::class)->store([
        'user_id' => $user->id,
        'manager_id' => $terminated->id,
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(DepartmentService::class)->store([
        'name' => 'Engineering',
        'manager_id' => $terminated->id,
    ]))->toThrow(ValidationException::class);
});

test('attendance clock in and out is unique per day', function (): void {
    app(HrSettingsService::class)->update([
        'hr.working_days' => '1,2,3,4,5,6,7',
        'hr.work_start_time' => '00:00',
        'hr.late_tolerance_minutes' => 1440,
    ]);

    $employee = Employee::factory()->create();
    $service = app(AttendanceService::class);

    $clockedIn = $service->clockIn($employee);

    expect($clockedIn->status)->toBe(AttendanceStatus::Present)
        ->and($clockedIn->checked_in_at)->not->toBeNull()
        ->and($clockedIn->checked_out_at)->toBeNull();

    expect(fn () => $service->clockIn($employee))->toThrow(ValidationException::class);

    $clockedOut = $service->clockOut($employee);

    expect($clockedOut->checked_out_at)->not->toBeNull();
    expect(fn () => $service->clockOut($employee))->toThrow(ValidationException::class);
});

test('leave requests can be submitted reviewed and cannot overlap when approved', function (): void {
    Event::fake([LeaveRequested::class, LeaveReviewed::class]);

    $reviewer = User::factory()->create();
    $reviewer->syncRoles(['admin']);
    $employee = Employee::factory()->create();
    $service = app(LeaveRequestService::class);

    $leave = $service->store([
        'employee_id' => $employee->id,
        'type' => LeaveType::Annual->value,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Family',
    ]);

    $annual = app(LeaveTypeService::class)->findActiveByCode(LeaveType::Annual->value);

    expect($leave->status)->toBe(LeaveStatus::Pending)
        ->and($leave->leave_type_id)->toBe($annual->id)
        ->and($leave->leaveType?->code)->toBe(LeaveType::Annual->value);
    Event::assertDispatched(LeaveRequested::class);

    $approved = $service->review($leave, LeaveStatus::Approved, $reviewer);

    expect($approved->status)->toBe(LeaveStatus::Approved)
        ->and($approved->reviewer_id)->toBe($reviewer->id);
    Event::assertDispatched(LeaveReviewed::class);

    expect(fn () => $service->store([
        'employee_id' => $employee->id,
        'type' => LeaveType::Sick->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]))->toThrow(ValidationException::class);
});

test('pending leave requests can be cancelled', function (): void {
    Event::fake([LeaveRequested::class]);

    $employee = Employee::factory()->create();
    $service = app(LeaveRequestService::class);

    $leave = $service->store([
        'employee_id' => $employee->id,
        'type' => LeaveType::Unpaid->value,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);

    $cancelled = $service->cancel($leave);

    expect($cancelled->status)->toBe(LeaveStatus::Cancelled);
    expect(fn () => $service->cancel($cancelled))->toThrow(ValidationException::class);
});

test('employee documents attach through media', function (): void {
    Storage::fake('public');

    $employee = Employee::factory()->create();
    $file = UploadedFile::fake()->image('contract.jpg', 200, 200);

    $media = app(EmployeeService::class)->addDocument($employee, $file, ['name' => 'Contract']);

    expect($media->collection_name)->toBe('documents')
        ->and($employee->getMedia('documents'))->toHaveCount(1);

    app(EmployeeService::class)->removeDocument($employee, $media);

    expect($employee->fresh()->getMedia('documents'))->toHaveCount(0);
});

test('hr permissions isolate admin from customer', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $manager = User::factory()->create();
    $manager->syncRoles(['manager']);

    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    $department = Department::factory()->create();
    $designation = Designation::factory()->create();
    $employee = Employee::factory()->create();
    $attendance = Attendance::factory()->create(['employee_id' => $employee->id]);
    $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id]);

    expect($admin->can('viewAny', Department::class))->toBeTrue()
        ->and($admin->can('create', Department::class))->toBeTrue()
        ->and($admin->can('viewAny', Designation::class))->toBeTrue()
        ->and($admin->can('create', Designation::class))->toBeTrue()
        ->and($admin->can('viewAny', Employee::class))->toBeTrue()
        ->and($admin->can('create', Employee::class))->toBeTrue()
        ->and($admin->can('viewAny', Attendance::class))->toBeTrue()
        ->and($admin->can('review', $leave))->toBeTrue()
        ->and($manager->can('hr.departments.manage'))->toBeTrue()
        ->and($manager->can('hr.designations.manage'))->toBeTrue()
        ->and($manager->can('hr.employees.create'))->toBeTrue()
        ->and($manager->can('hr.attendance.manage'))->toBeTrue()
        ->and($manager->can('hr.leave.manage'))->toBeTrue()
        ->and($manager->can('hr.leave.approve'))->toBeTrue()
        ->and($manager->can('hr.payroll.approve'))->toBeTrue()
        ->and($manager->can('hr.settings.update'))->toBeTrue()
        ->and($customer->can('viewAny', Department::class))->toBeFalse()
        ->and($customer->can('create', Designation::class))->toBeFalse()
        ->and($customer->can('create', Employee::class))->toBeFalse()
        ->and($customer->can('update', $employee))->toBeFalse()
        ->and($customer->can('delete', $department))->toBeFalse()
        ->and($customer->can('update', $attendance))->toBeFalse()
        ->and($customer->can('review', $leave))->toBeFalse();
});

test('employee create and assignment changes write employment history', function (): void {
    Event::fake([EmployeeCreated::class]);

    $user = User::factory()->create();
    $department = Department::factory()->create();
    $service = app(EmployeeService::class);

    $employee = $service->store([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'job_title' => 'Engineer',
        'hired_at' => now()->toDateString(),
    ]);

    expect(EmploymentRecord::query()->where('employee_id', $employee->id)->where('change_type', EmploymentChangeType::Hired)->exists())->toBeTrue();

    $service->update($employee, ['job_title' => 'Lead Engineer']);

    expect(EmploymentRecord::query()->where('employee_id', $employee->id)->where('change_type', EmploymentChangeType::AssignmentChanged)->exists())->toBeTrue()
        ->and($service->employmentHistory($employee))->not->toBeEmpty();
});

test('clock out records overtime minutes when overtime is enabled', function (): void {
    $this->travelTo(Carbon::parse('2026-08-18 09:00:00'));

    app(HrSettingsService::class)->update([
        'hr.working_days' => '1,2,3,4,5,6,7',
        'hr.work_start_time' => '09:00',
        'hr.overtime.enabled' => true,
        'hr.working_hours_per_day' => 8,
    ]);

    $employee = Employee::factory()->create();
    $service = app(AttendanceService::class);

    $service->clockIn($employee);
    $this->travelTo(Carbon::parse('2026-08-18 19:00:00'));
    $clockedOut = $service->clockOut($employee);

    expect($clockedOut->overtime_minutes)->toBe(120);
});

test('assigned work schedule overrides settings for clock in days', function (): void {
    $this->travelTo(Carbon::parse('2026-08-23 09:00:00'));

    app(HrSettingsService::class)->update([
        'hr.working_days' => '1,2,3,4,5,6,7',
    ]);

    $schedule = app(WorkScheduleService::class)->store([
        'name' => 'Weekdays',
        'days' => collect(range(1, 5))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])->all(),
    ]);

    $employee = Employee::factory()->create(['work_schedule_id' => $schedule->id]);

    expect(fn () => app(AttendanceService::class)->clockIn($employee))->toThrow(ValidationException::class);
});

test('overtime policy caps daily overtime and snapshots holiday rates', function (): void {
    $this->travelTo(Carbon::parse('2026-08-18 09:00:00'));

    app(HrSettingsService::class)->update([
        'hr.working_days' => '1,2,3,4,5,6,7',
        'hr.work_start_time' => '09:00',
        'hr.overtime.enabled' => true,
        'hr.working_hours_per_day' => 8,
        'hr.overtime.rate_percent' => 150,
    ]);

    app(OvertimePolicyService::class)->store([
        'name' => 'Standard OT',
        'is_default' => true,
        'weekday_rate_percent' => 150,
        'holiday_rate_percent' => 200,
        'max_daily_minutes' => 60,
    ]);

    app(PublicHolidayService::class)->store([
        'observed_on' => '2026-08-18',
        'name' => 'Test Holiday',
    ]);

    $employee = Employee::factory()->create();
    $service = app(AttendanceService::class);

    $service->clockIn($employee);
    $this->travelTo(Carbon::parse('2026-08-18 19:00:00'));
    $clockedOut = $service->clockOut($employee);

    expect($clockedOut->overtime_minutes)->toBe(60)
        ->and($clockedOut->overtime_rate_percent)->toBe(200);
});
