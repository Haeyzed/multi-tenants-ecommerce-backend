<?php

declare(strict_types=1);

use App\Enums\Tenant\Accounting\AccountType;
use App\Enums\Tenant\Accounting\JournalEntryStatus;
use App\Enums\Tenant\HR\AttendanceStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Events\PayrollPaid;
use App\Events\PayrollProcessed;
use App\Events\PayslipAvailable;
use App\Models\Tenant\Account;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeSalary;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\PayrollItem;
use App\Models\Tenant\PayrollRun;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\EmployeeSalaryService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\PayrollRunService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
        '2026_08_18_001426_create_employee_salaries_table.php',
        '2026_08_18_001429_create_payroll_runs_table.php',
        '2026_08_18_001431_create_payroll_items_table.php',
        '2026_08_18_001434_create_payroll_item_lines_table.php',
        '2026_08_15_060016_create_accounts_table.php',
        '2026_08_15_060017_create_journal_entries_table.php',
        '2026_08_15_060018_create_journal_entry_lines_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_18_003141_create_leave_types_table.php',
        '2026_08_18_003144_create_leave_balances_table.php',
        '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php',
        '2026_08_18_003148_add_manager_id_to_departments_table.php',
    ];

    foreach ($migrations as $file) {
        $table = match ($file) {
            '2026_08_16_161019_create_departments_table.php' => 'departments',
            '2026_08_16_161032_create_employees_table.php' => 'employees',
            '2026_08_17_211947_create_designations_table.php' => 'designations',
            '2026_08_17_211951_add_designation_id_to_employees_table.php' => null,
            '2026_08_17_211954_create_attendances_table.php' => 'attendances',
            '2026_08_17_212000_create_leave_requests_table.php' => 'leave_requests',
            '2026_08_18_001426_create_employee_salaries_table.php' => 'employee_salaries',
            '2026_08_18_001429_create_payroll_runs_table.php' => 'payroll_runs',
            '2026_08_18_001431_create_payroll_items_table.php' => 'payroll_items',
            '2026_08_18_001434_create_payroll_item_lines_table.php' => 'payroll_item_lines',
            '2026_08_15_060016_create_accounts_table.php' => 'accounts',
            '2026_08_15_060017_create_journal_entries_table.php' => 'journal_entries',
            '2026_08_15_060018_create_journal_entry_lines_table.php' => 'journal_entry_lines',
            '2026_08_15_060001_create_commerce_settings_table.php' => 'commerce_settings',
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
});

test('employee salary can be configured and updated', function (): void {
    $employee = Employee::factory()->create();
    $service = app(EmployeeSalaryService::class);

    $salary = $service->upsert($employee, [
        'base_salary' => '150000.00',
        'currency' => 'ngn',
        'effective_from' => now()->toDateString(),
    ]);

    expect($salary->base_salary)->toBe('150000.00')
        ->and($salary->currency)->toBe('NGN')
        ->and($employee->fresh()->salary)->not->toBeNull();

    $updated = $service->upsert($employee, [
        'base_salary' => '175000.00',
    ]);

    expect($updated->base_salary)->toBe('175000.00')
        ->and(EmployeeSalary::query()->count())->toBe(1);

    expect(fn () => $service->upsert($employee, ['base_salary' => '0']))->toThrow(ValidationException::class);
});

test('payroll run generates payslips with absence and unpaid leave deductions', function (): void {
    $periodStart = now()->startOfMonth()->toDateString();
    $periodEnd = now()->endOfMonth()->toDateString();

    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '220000.00',
        'effective_from' => now()->subMonth()->toDateString(),
    ]);

    $weekday = now()->startOfMonth();
    while (! $weekday->isWeekday()) {
        $weekday->addDay();
    }

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => $weekday->toDateString(),
        'status' => AttendanceStatus::Absent,
    ]);

    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'type' => LeaveType::Unpaid->value,
        'status' => LeaveStatus::Approved,
        'start_date' => $weekday->copy()->addDays(2)->toDateString(),
        'end_date' => $weekday->copy()->addDays(2)->toDateString(),
    ]);

    $service = app(PayrollRunService::class);
    $run = $service->create([
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'currency' => 'NGN',
    ]);

    expect($run->status)->toBe(PayrollRunStatus::Draft)
        ->and($run->employee_count)->toBe(1)
        ->and($run->items)->toHaveCount(1);

    $item = $run->items->first();
    expect($item)->not->toBeNull()
        ->and($item->absent_days)->toBeGreaterThanOrEqual(1)
        ->and($item->unpaid_leave_days)->toBeGreaterThanOrEqual(1)
        ->and((float) $item->deduction_total)->toBeGreaterThan(0)
        ->and((float) $item->net_pay)->toBeLessThan((float) $item->gross_pay)
        ->and($item->lines)->not->toBeEmpty();
});

test('payroll run can be processed paid and posted to accounting', function (): void {
    Event::fake([PayrollProcessed::class, PayrollPaid::class, PayslipAvailable::class]);

    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '100000.00',
    ]);

    $expense = Account::factory()->create(['type' => AccountType::Expense]);
    $payable = Account::factory()->create(['type' => AccountType::Liability]);

    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $service = app(PayrollRunService::class);
    $run = $service->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $processed = $service->process($run, $admin);

    expect($processed->status)->toBe(PayrollRunStatus::Processed)
        ->and($processed->processed_by)->toBe($admin->id);

    expect(fn () => $service->generate($processed))->toThrow(ValidationException::class);

    $paid = $service->pay($processed, $admin, [
        'post_to_accounting' => true,
        'expense_account_id' => $expense->id,
        'payable_account_id' => $payable->id,
    ]);

    expect($paid->status)->toBe(PayrollRunStatus::Paid)
        ->and($paid->paid_by)->toBe($admin->id);

    $journal = JournalEntry::query()
        ->where('source_type', $paid->getMorphClass())
        ->where('source_id', $paid->id)
        ->where('entry_type', 'payroll')
        ->first();

    expect($journal)->not->toBeNull()
        ->and($journal->status)->toBe(JournalEntryStatus::Posted);
});

test('overlapping payroll periods are rejected', function (): void {
    Employee::factory()->create()->salary()->create([
        'base_salary' => '50000.00',
        'effective_from' => now()->subMonth()->toDateString(),
    ]);

    $service = app(PayrollRunService::class);
    $service->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    expect(fn () => $service->create([
        'period_start' => now()->startOfMonth()->addDays(5)->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]))->toThrow(ValidationException::class);
});

test('payroll permissions isolate managers from customers and allow own payslip access', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $manager = User::factory()->create();
    $manager->syncRoles(['manager']);

    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    $employeeUser = User::factory()->create();
    $employeeUser->syncRoles(['customer']);
    $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);

    $run = PayrollRun::factory()->create();
    $item = PayrollItem::factory()->create([
        'payroll_run_id' => $run->id,
        'employee_id' => $employee->id,
    ]);

    expect($admin->can('hr.payroll.manage'))->toBeTrue()
        ->and($manager->can('hr.payroll.manage'))->toBeTrue()
        ->and($manager->can('viewAny', PayrollRun::class))->toBeTrue()
        ->and($customer->can('viewAny', PayrollRun::class))->toBeFalse()
        ->and($customer->can('create', PayrollRun::class))->toBeFalse()
        ->and($employeeUser->can('viewSalary', $employee))->toBeTrue()
        ->and($customer->can('viewSalary', $employee))->toBeFalse()
        ->and($employeeUser->can('view', $item))->toBeTrue()
        ->and($customer->can('view', $item))->toBeFalse();
});

test('disabled payroll setting blocks run creation', function (): void {
    app(HrSettingsService::class)->update(['hr.payroll.enabled' => false]);

    expect(fn () => app(PayrollRunService::class)->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]))->toThrow(ValidationException::class);
});

test('payroll approval setting routes process into pending approval', function (): void {
    Event::fake([PayrollProcessed::class, PayslipAvailable::class]);

    app(HrSettingsService::class)->update(['hr.payroll.approval_required' => true]);

    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '80000.00',
    ]);

    $admin = User::factory()->create();
    $service = app(PayrollRunService::class);
    $run = $service->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $pending = $service->process($run, $admin);

    expect($pending->status)->toBe(PayrollRunStatus::PendingApproval);
    expect(fn () => $service->pay($pending, $admin))->toThrow(ValidationException::class);

    $approved = $service->approve($pending, $admin);

    expect($approved->status)->toBe(PayrollRunStatus::Processed);
});
