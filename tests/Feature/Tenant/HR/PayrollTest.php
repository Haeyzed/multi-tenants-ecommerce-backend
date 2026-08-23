<?php

declare(strict_types=1);

use App\Enums\Tenant\Accounting\AccountType;
use App\Enums\Tenant\Accounting\JournalEntryStatus;
use App\Enums\Tenant\HR\AttendanceStatus;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\PayrollLineType;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Enums\Tenant\HR\SalaryComponentCalculation;
use App\Events\PayrollPaid;
use App\Events\PayrollProcessed;
use App\Events\PayslipAvailable;
use App\Models\Tenant\Account;
use App\Models\Tenant\HR\Attendance;
use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\HR\EmployeeSalary;
use App\Models\Tenant\HR\EmployeeSalaryRevision;
use App\Models\Tenant\HR\LeaveRequest;
use App\Models\Tenant\HR\LeaveType as LeaveTypeModel;
use App\Models\Tenant\HR\PayrollItem;
use App\Models\Tenant\HR\PayrollRun;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\EmployeeSalaryService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\OvertimePolicyService;
use App\Services\Tenant\HR\PayrollRunService;
use App\Services\Tenant\HR\PayslipPdfService;
use App\Services\Tenant\HR\TaxTableService;
use App\Services\Tenant\HR\WorkScheduleService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        '2026_08_19_182044_add_leave_type_id_to_leave_requests_table.php',
        '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php',
        '2026_08_18_003148_add_manager_id_to_departments_table.php',
        '2026_08_18_014811_create_employee_salary_components_table.php',
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
        '2026_08_18_142504_create_employee_salary_revisions_table.php',
        '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php',
        '2026_08_18_145939_add_clock_source_and_location_to_attendances_table.php',
        '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php',
        '2026_08_18_145952_add_nibss_disbursement_columns_to_payroll_runs_table.php',
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
            '2026_08_19_182044_add_leave_type_id_to_leave_requests_table.php' => null,
            '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php' => null,
            '2026_08_18_003148_add_manager_id_to_departments_table.php' => null,
            '2026_08_18_014811_create_employee_salary_components_table.php' => 'employee_salary_components',
            '2026_08_18_014813_create_payroll_periods_table.php' => 'payroll_periods',
            '2026_08_18_014816_add_payroll_period_id_to_payroll_runs_table.php' => null,
            '2026_08_18_014825_add_leave_carry_over_and_overtime_columns.php' => null,
            '2026_08_18_134108_create_work_schedules_table.php' => 'work_schedules',
            '2026_08_18_134110_create_work_schedule_days_table.php' => 'work_schedule_days',
            '2026_08_18_134113_create_overtime_policies_table.php' => 'overtime_policies',
            '2026_08_18_134116_create_public_holidays_table.php' => 'public_holidays',
            '2026_08_18_134118_create_tax_tables_table.php' => 'tax_tables',
            '2026_08_18_134122_create_tax_table_bands_table.php' => 'tax_table_bands',
            '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php' => null,
            '2026_08_18_142459_add_bank_and_tax_columns_to_employees_table.php' => null,
            '2026_08_18_142501_add_payslip_snapshot_columns_to_payroll_items_table.php' => null,
            '2026_08_18_142504_create_employee_salary_revisions_table.php' => 'employee_salary_revisions',
            '2026_08_18_145936_add_weekly_overtime_columns_to_overtime_policies_table.php' => null,
            '2026_08_18_145939_add_clock_source_and_location_to_attendances_table.php' => null,
            '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php' => null,
            '2026_08_18_145952_add_nibss_disbursement_columns_to_payroll_runs_table.php' => null,
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

        if ($file === '2026_08_18_014816_add_payroll_period_id_to_payroll_runs_table.php' && Schema::hasColumn('payroll_runs', 'payroll_period_id')) {
            continue;
        }

        if ($file === '2026_08_19_182044_add_leave_type_id_to_leave_requests_table.php' && Schema::hasColumn('leave_requests', 'leave_type_id')) {
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
        ->and(EmployeeSalary::query()->count())->toBe(1)
        ->and(EmployeeSalaryRevision::query()->where('employee_id', $employee->id)->count())->toBe(1);

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

    $unpaidLeaveType = LeaveTypeModel::factory()->unpaid()->create();

    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $unpaidLeaveType->id,
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

test('salary components including tax percent of gross appear on generated payslips', function (): void {
    $employee = Employee::factory()->create();
    app(EmployeeSalaryService::class)->upsert($employee, [
        'base_salary' => '100000.00',
        'components' => [
            [
                'type' => PayrollLineType::Earning->value,
                'calculation' => SalaryComponentCalculation::Fixed->value,
                'code' => 'housing',
                'label' => 'Housing allowance',
                'amount' => '20000.00',
            ],
            [
                'type' => PayrollLineType::Deduction->value,
                'calculation' => SalaryComponentCalculation::Percent->value,
                'code' => 'pension',
                'label' => 'Pension',
                'amount' => '5',
            ],
            [
                'type' => PayrollLineType::Deduction->value,
                'calculation' => SalaryComponentCalculation::Percent->value,
                'code' => 'paye',
                'label' => 'PAYE',
                'amount' => '10',
                'is_tax' => true,
            ],
        ],
    ]);

    $run = app(PayrollRunService::class)->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $item = $run->items->first();
    $codes = $item?->lines->pluck('code') ?? collect();

    expect($item)->not->toBeNull()
        ->and((float) $item->gross_pay)->toBe(120000.0)
        ->and((float) $item->deduction_total)->toBe(17000.0)
        ->and((float) $item->net_pay)->toBe(103000.0)
        ->and($codes)->toContain('housing')
        ->and($codes)->toContain('pension')
        ->and($codes)->toContain('paye');
});

test('enabled overtime adds an earning line from attendance minutes', function (): void {
    app(HrSettingsService::class)->update([
        'hr.overtime.enabled' => true,
        'hr.working_hours_per_day' => 8,
        'hr.overtime.rate_percent' => 150,
    ]);

    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '100000.00',
    ]);

    $weekday = now()->startOfMonth();
    while (! $weekday->isWeekday()) {
        $weekday->addDay();
    }

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => $weekday->toDateString(),
        'status' => AttendanceStatus::Present,
        'overtime_minutes' => 120,
    ]);

    $run = app(PayrollRunService::class)->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $item = $run->items->first();

    expect($item)->not->toBeNull()
        ->and($item->overtime_minutes)->toBe(120)
        ->and((float) $item->gross_pay)->toBeGreaterThan(100000)
        ->and($item->lines->pluck('code'))->toContain('overtime');
});

test('payroll runs without dates use the current period from frequency and payment day', function (): void {
    $this->travelTo(Carbon::parse('2026-08-18 10:00:00'));

    EmployeeSalary::factory()->create([
        'base_salary' => '75000.00',
    ]);

    $run = app(PayrollRunService::class)->create([]);

    expect($run->period_start->toDateString())->toBe('2026-08-01')
        ->and($run->period_end->toDateString())->toBe('2026-08-31')
        ->and($run->payroll_period_id)->not->toBeNull()
        ->and($run->payrollPeriod?->payment_date?->toDateString())->toBe('2026-08-25');
});

test('payment day schedules a draft run once for the current period', function (): void {
    $this->travelTo(Carbon::parse('2026-08-25 09:00:00'));

    EmployeeSalary::factory()->create([
        'base_salary' => '75000.00',
    ]);

    $service = app(PayrollRunService::class);

    $scheduled = $service->scheduleCurrentPeriodRun();
    $duplicate = $service->scheduleCurrentPeriodRun();

    expect($scheduled)->not->toBeNull()
        ->and($scheduled->status)->toBe(PayrollRunStatus::Draft)
        ->and($scheduled->payroll_period_id)->not->toBeNull()
        ->and($duplicate)->toBeNull();
});

test('paying a payroll run posts to accounting when hr account defaults are set', function (): void {
    Event::fake([PayrollProcessed::class, PayrollPaid::class, PayslipAvailable::class]);

    $expense = Account::factory()->create(['type' => AccountType::Expense]);
    $payable = Account::factory()->create(['type' => AccountType::Liability]);

    app(HrSettingsService::class)->update([
        'hr.payroll.expense_account_id' => $expense->id,
        'hr.payroll.payable_account_id' => $payable->id,
    ]);

    EmployeeSalary::factory()->create([
        'base_salary' => '100000.00',
    ]);

    $admin = User::factory()->create();
    $service = app(PayrollRunService::class);
    $run = $service->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);
    $processed = $service->process($run, $admin);
    $paid = $service->pay($processed, $admin);

    $journal = JournalEntry::query()
        ->where('source_type', $paid->getMorphClass())
        ->where('source_id', $paid->id)
        ->where('entry_type', 'payroll')
        ->first();

    expect($paid->status)->toBe(PayrollRunStatus::Paid)
        ->and($journal)->not->toBeNull()
        ->and($journal->status)->toBe(JournalEntryStatus::Posted);
});

test('enabled PAYE uses the country tax table and skips salary tax components', function (): void {
    app(TaxTableService::class)->ensureDefaults();
    app(HrSettingsService::class)->update([
        'hr.payroll.tax_enabled' => true,
    ]);

    $employee = Employee::factory()->create();
    app(EmployeeSalaryService::class)->upsert($employee, [
        'base_salary' => '100000.00',
        'components' => [
            [
                'type' => PayrollLineType::Deduction->value,
                'calculation' => SalaryComponentCalculation::Percent->value,
                'code' => 'paye',
                'label' => 'PAYE percent',
                'amount' => '10',
                'is_tax' => true,
            ],
        ],
    ]);

    $run = app(PayrollRunService::class)->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $item = $run->items->first();
    $paye = $item?->lines->firstWhere('code', 'paye');

    expect($item)->not->toBeNull()
        ->and($item->lines->where('code', 'paye')->count())->toBe(1)
        ->and($paye)->not->toBeNull()
        ->and((float) $paye->amount)->toBe(6500.0)
        ->and((float) $item->deduction_total)->toBe(6500.0);
});

test('overtime policy pays weekend work at a higher rate than weekday work', function (): void {
    app(HrSettingsService::class)->update([
        'hr.overtime.enabled' => true,
        'hr.working_hours_per_day' => 8,
        'hr.overtime.rate_percent' => 150,
    ]);

    $policy = app(OvertimePolicyService::class)->store([
        'name' => 'Differential OT',
        'is_default' => true,
        'weekday_rate_percent' => 150,
        'weekend_rate_percent' => 200,
        'holiday_rate_percent' => 200,
    ]);

    $schedule = app(WorkScheduleService::class)->store([
        'name' => 'Office week',
        'overtime_policy_id' => $policy->id,
        'days' => collect(range(1, 5))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])->all(),
    ]);

    $weekdayEmployee = Employee::factory()->create(['work_schedule_id' => $schedule->id]);
    $weekendEmployee = Employee::factory()->create(['work_schedule_id' => $schedule->id]);

    EmployeeSalary::factory()->create([
        'employee_id' => $weekdayEmployee->id,
        'base_salary' => '100000.00',
    ]);
    EmployeeSalary::factory()->create([
        'employee_id' => $weekendEmployee->id,
        'base_salary' => '100000.00',
    ]);

    Attendance::factory()->create([
        'employee_id' => $weekdayEmployee->id,
        'work_date' => '2026-08-17',
        'status' => AttendanceStatus::Present,
        'overtime_minutes' => 120,
        'overtime_rate_percent' => 0,
    ]);

    Attendance::factory()->create([
        'employee_id' => $weekendEmployee->id,
        'work_date' => '2026-08-22',
        'status' => AttendanceStatus::Present,
        'overtime_minutes' => 120,
        'overtime_rate_percent' => 0,
    ]);

    $run = app(PayrollRunService::class)->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);

    $weekdayItem = $run->items->firstWhere('employee_id', $weekdayEmployee->id);
    $weekendItem = $run->items->firstWhere('employee_id', $weekendEmployee->id);
    $weekdayOvertime = $weekdayItem?->lines->firstWhere('code', 'overtime');
    $weekendOvertime = $weekendItem?->lines->firstWhere('code', 'overtime');

    expect($weekdayOvertime)->not->toBeNull()
        ->and($weekendOvertime)->not->toBeNull()
        ->and((float) $weekendOvertime->amount)->toBeGreaterThan((float) $weekdayOvertime->amount);
});

test('mid-period hire prorates base salary against scheduled working days', function (): void {
    $employee = Employee::factory()->create([
        'hired_at' => '2026-08-17',
        'bank_name' => 'Access Bank',
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'Ada Lovelace',
        'tax_id' => '1234567890',
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

    expect($item)->not->toBeNull()
        ->and($item->scheduled_days)->toBeGreaterThan($item->working_days)
        ->and($item->working_days)->toBeGreaterThan(0)
        ->and((float) $item->base_salary)->toBeLessThan(100000)
        ->and($item->account_number)->toBe('0123456789')
        ->and($item->bank_code)->toBe('044');

    $register = app(PayrollRunService::class)->paymentRegister($run);
    expect($register[0]['account_number'] ?? null)->toBe('0123456789');

    $pdf = app(PayslipPdfService::class)->output($item);
    expect(str_starts_with($pdf, '%PDF'))->toBeTrue();
});

test('terminated employees still receive a prorated final payslip', function (): void {
    $employee = Employee::factory()->create([
        'employment_status' => EmploymentStatus::Terminated,
        'hired_at' => '2026-01-01',
        'terminated_at' => '2026-08-07',
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

    expect($run->employee_count)->toBe(1)
        ->and($item)->not->toBeNull()
        ->and($item->working_days)->toBeGreaterThan(0)
        ->and($item->working_days)->toBeLessThan($item->scheduled_days)
        ->and((float) $item->base_salary)->toBeLessThan(100000);
});

test('payroll journals split gross expense from paye payable', function (): void {
    Event::fake([PayrollProcessed::class, PayrollPaid::class, PayslipAvailable::class]);

    app(TaxTableService::class)->ensureDefaults();
    app(HrSettingsService::class)->update(['hr.payroll.tax_enabled' => true]);

    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'base_salary' => '100000.00',
    ]);

    $expense = Account::factory()->create(['type' => AccountType::Expense]);
    $payable = Account::factory()->create(['type' => AccountType::Liability]);
    $taxPayable = Account::factory()->create(['type' => AccountType::Liability]);
    $admin = User::factory()->create();

    $service = app(PayrollRunService::class);
    $run = $service->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);
    $processed = $service->process($run, $admin);
    $paid = $service->pay($processed, $admin, [
        'post_to_accounting' => true,
        'expense_account_id' => $expense->id,
        'payable_account_id' => $payable->id,
        'tax_payable_account_id' => $taxPayable->id,
    ]);

    $journal = JournalEntry::query()
        ->where('source_type', $paid->getMorphClass())
        ->where('source_id', $paid->id)
        ->where('entry_type', 'payroll')
        ->with('lines')
        ->first();

    expect($journal)->not->toBeNull()
        ->and($journal->lines)->toHaveCount(3)
        ->and((float) $journal->lines->firstWhere('account_id', $expense->id)?->debit)->toBe((float) $paid->gross_total)
        ->and((float) $journal->lines->firstWhere('account_id', $taxPayable->id)?->credit)->toBe(6500.0)
        ->and((float) $journal->lines->firstWhere('account_id', $payable->id)?->credit)->toBe((float) $paid->net_total);
});
