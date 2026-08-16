<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\DepartmentService;
use App\Services\Tenant\HR\EmployeeService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! Schema::hasTable('departments')) {
        foreach ([
            '2026_08_16_161019_create_departments_table.php',
            '2026_08_16_161032_create_employees_table.php',
        ] as $file) {
            $this->artisan('migrate', [
                '--path' => database_path('migrations/tenant/'.$file),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
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

test('employee service links to an existing user once', function (): void {
    $user = User::factory()->create();
    $department = Department::factory()->create();
    $service = app(EmployeeService::class);

    $employee = $service->store([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'job_title' => 'Backend Engineer',
        'employee_number' => 'EMP-100',
        'employment_status' => EmploymentStatus::Active->value,
    ]);

    expect($employee->user_id)->toBe($user->id)
        ->and($employee->department_id)->toBe($department->id)
        ->and($employee->employment_status)->toBe(EmploymentStatus::Active)
        ->and($user->fresh()->employee)->not->toBeNull();

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

test('hr permissions isolate admin from customer', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $manager = User::factory()->create();
    $manager->syncRoles(['manager']);

    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    $department = Department::factory()->create();
    $employee = Employee::factory()->create();

    expect($admin->can('viewAny', Department::class))->toBeTrue()
        ->and($admin->can('create', Department::class))->toBeTrue()
        ->and($admin->can('viewAny', Employee::class))->toBeTrue()
        ->and($admin->can('create', Employee::class))->toBeTrue()
        ->and($manager->can('hr.departments.manage'))->toBeTrue()
        ->and($manager->can('hr.employees.create'))->toBeTrue()
        ->and($customer->can('viewAny', Department::class))->toBeFalse()
        ->and($customer->can('create', Employee::class))->toBeFalse()
        ->and($customer->can('update', $employee))->toBeFalse()
        ->and($customer->can('delete', $department))->toBeFalse();
});
