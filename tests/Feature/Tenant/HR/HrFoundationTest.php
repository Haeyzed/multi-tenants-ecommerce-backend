<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType;
use App\Events\EmployeeCreated;
use App\Events\LeaveRequested;
use App\Events\LeaveReviewed;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Department;
use App\Models\Tenant\Designation;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\AttendanceService;
use App\Services\Tenant\HR\DepartmentService;
use App\Services\Tenant\HR\DesignationService;
use App\Services\Tenant\HR\EmployeeService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\LeaveRequestService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php',
        '2026_08_18_003148_add_manager_id_to_departments_table.php',
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

    expect($leave->status)->toBe(LeaveStatus::Pending);
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
