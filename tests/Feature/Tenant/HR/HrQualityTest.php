<?php

declare(strict_types=1);

use App\Enums\Landlord\BillingInterval;
use App\Enums\Landlord\TenantStatus;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Events\EmployeeCreated;
use App\Events\EmployeeStatusChanged;
use App\Events\LeaveRequested;
use App\Events\LeaveReviewed;
use App\Events\PayrollPaid;
use App\Events\PayrollProcessed;
use App\Jobs\SendInterviewRemindersJob;
use App\Listeners\Notification\SendLeaveRequestedNotification;
use App\Listeners\Notification\SendPayrollPaidNotification;
use App\Models\HR\Employee;
use App\Models\HR\HrActivity;
use App\Models\HR\Interview;
use App\Models\HR\PayrollItem;
use App\Models\HR\PayrollRun;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Services\Landlord\Subscription\SubscriptionService;
use App\Services\Landlord\Tenant\TenantService;
use App\Services\Notification\NotificationService;
use App\Services\Tenant\HR\EmployeeService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\InterviewService;
use App\Services\Tenant\HR\JobApplicationService;
use App\Services\Tenant\HR\JobOpeningService;
use App\Services\Tenant\HR\LeaveRequestService;
use App\Services\Tenant\HR\PayrollRunService;
use App\Support\RecruitmentNotifier;
use Database\Seeders\Landlord\FeatureSeeder;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Database\Seeders\Tenant\PermissionSeeder as TenantPermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder as TenantRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $extraMigrations
 */
function runHrQualityMigrations(array $extraMigrations = []): void
{
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
        '2026_08_18_001429_create_payroll_runs_table.php',
        '2026_08_18_001431_create_payroll_items_table.php',
        '2026_08_18_001434_create_payroll_item_lines_table.php',
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
        '2026_08_18_014813_create_payroll_periods_table.php',
        '2026_08_18_014816_add_payroll_period_id_to_payroll_runs_table.php',
        '2026_08_18_142501_add_payslip_snapshot_columns_to_payroll_items_table.php',
        '2026_08_18_145952_add_nibss_disbursement_columns_to_payroll_runs_table.php',
        '2026_08_20_001849_create_hr_activities_table.php',
        ...$extraMigrations,
    ];

    foreach ($migrations as $file) {
        $table = match ($file) {
            '2026_08_15_060001_create_commerce_settings_table.php' => 'commerce_settings',
            '2026_08_16_161019_create_departments_table.php' => 'departments',
            '2026_08_16_161032_create_employees_table.php' => 'employees',
            '2026_08_17_211947_create_designations_table.php' => 'designations',
            '2026_08_17_211954_create_attendances_table.php' => 'attendances',
            '2026_08_17_212000_create_leave_requests_table.php' => 'leave_requests',
            '2026_08_18_003141_create_leave_types_table.php' => 'leave_types',
            '2026_08_18_003144_create_leave_balances_table.php' => 'leave_balances',
            '2026_08_18_014822_create_employment_records_table.php' => 'employment_records',
            '2026_08_18_134108_create_work_schedules_table.php' => 'work_schedules',
            '2026_08_18_134110_create_work_schedule_days_table.php' => 'work_schedule_days',
            '2026_08_18_134113_create_overtime_policies_table.php' => 'overtime_policies',
            '2026_08_18_134116_create_public_holidays_table.php' => 'public_holidays',
            '2026_08_18_134118_create_tax_tables_table.php' => 'tax_tables',
            '2026_08_18_134122_create_tax_table_bands_table.php' => 'tax_table_bands',
            '2026_08_18_001429_create_payroll_runs_table.php' => 'payroll_runs',
            '2026_08_18_001431_create_payroll_items_table.php' => 'payroll_items',
            '2026_08_18_001434_create_payroll_item_lines_table.php' => 'payroll_item_lines',
            '2026_08_18_014813_create_payroll_periods_table.php' => 'payroll_periods',
            '2026_08_20_001849_create_hr_activities_table.php' => 'hr_activities',
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

        if ($file === '2026_08_18_014816_add_payroll_period_id_to_payroll_runs_table.php' && Schema::hasColumn('payroll_runs', 'payroll_period_id')) {
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

        if ($file === '2026_08_18_145952_add_nibss_disbursement_columns_to_payroll_runs_table.php' && Schema::hasColumn('payroll_runs', 'nibss_batch_reference')) {
            continue;
        }

        Artisan::call('migrate', [
            '--path' => database_path('migrations/tenant/'.$file),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    app(TenantPermissionSeeder::class)->run();
    app(TenantRoleSeeder::class)->run();
}

/**
 * @return array{tenant: Tenant, domain: string}
 */
function provisionQualityTenant(): array
{
    $suffix = Str::lower(Str::random(8));
    $domain = "hr-quality-{$suffix}.test";

    $tenant = app(TenantService::class)->store([
        'name' => 'HR Quality Tenant',
        'slug' => "hr-quality-{$suffix}",
        'email' => "owner-{$suffix}@example.com",
        'status' => TenantStatus::Active->value,
        'is_active' => true,
        'domain' => $domain,
        'admin' => [
            'first_name' => 'Admin',
            'last_name' => 'Quality',
            'email' => "admin-{$suffix}@example.com",
            'password' => 'Password1!',
        ],
        'profile' => [
            'display_name' => 'HR Quality Tenant',
            'is_public' => true,
        ],
    ]);

    return [
        'tenant' => $tenant->fresh(['domains']) ?? $tenant,
        'domain' => $domain,
    ];
}

/**
 * @param  array<string, array{enabled: bool, limit: int|null}>  $limits
 */
function subscribeTenantWithLimits(Tenant $tenant, array $limits): void
{
    $plan = Plan::query()->create([
        'name' => 'HR Quality Plan',
        'slug' => 'hr-quality-'.uniqid(),
        'description' => 'Plan for HR quality tests',
        'price' => '0.00',
        'currency' => 'NGN',
        'billing_interval' => BillingInterval::Monthly,
        'billing_interval_count' => 1,
        'trial_days' => 0,
        'is_active' => true,
        'is_public' => false,
        'sort_order' => 99,
    ]);

    $sync = [];

    foreach ($limits as $slug => $config) {
        $feature = Feature::query()->where('slug', $slug)->firstOrFail();
        $sync[$feature->id] = [
            'is_enabled' => $config['enabled'],
            'limit' => $config['limit'],
        ];
    }

    $plan->features()->sync($sync);

    app(SubscriptionService::class)->subscribe($tenant, $plan);
}

afterEach(function (): void {
    Carbon::setTestNow();

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    foreach (File::glob(database_path('testing_tenant_hr_quality_*')) ?: [] as $database) {
        @File::delete($database);
    }

    foreach (File::glob(database_path('testing_tenant_ats_*')) ?: [] as $database) {
        @File::delete($database);
    }
});

describe('HR audit logging', function (): void {
    beforeEach(function (): void {
        config([
            'tenancy.database.prefix' => 'testing_tenant_hr_quality_',
            'tenancy.database.suffix' => '',
            'tenancy.bootstrappers' => array_values(array_filter(
                config('tenancy.bootstrappers'),
                static fn (string $bootstrapper): bool => ! str_contains($bootstrapper, 'CacheTenancyBootstrapper'),
            )),
        ]);

        runHrQualityMigrations();
    });

    test('employee lifecycle actions are recorded without sensitive fields', function (): void {
        Event::fake([EmployeeCreated::class, EmployeeStatusChanged::class]);

        $employee = app(EmployeeService::class)->store([
            'user_id' => User::factory()->create()->id,
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
        ]);

        expect(HrActivity::query()->where('action', 'created')->count())->toBe(1)
            ->and(HrActivity::query()->first()?->meta)->not->toHaveKey('account_number');

        app(EmployeeService::class)->update($employee, [
            'employment_status' => EmploymentStatus::OnLeave->value,
        ]);

        $statusActivity = HrActivity::query()->where('action', 'status_changed')->first();

        expect($statusActivity)->not->toBeNull()
            ->and($statusActivity->meta['from'])->toBe(EmploymentStatus::Active->value)
            ->and($statusActivity->meta['to'])->toBe(EmploymentStatus::OnLeave->value);
    });

    test('leave request actions are recorded on submit review and cancel', function (): void {
        Event::fake([LeaveRequested::class, LeaveReviewed::class]);

        app(HrSettingsService::class)->update([
            'hr.leave.approval_required' => true,
        ]);

        $reviewer = User::factory()->create();
        $employee = Employee::factory()->create();
        $service = app(LeaveRequestService::class);

        $leave = $service->store([
            'employee_id' => $employee->id,
            'type' => LeaveType::Annual->value,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'reason' => 'Should not appear in audit meta',
        ]);

        expect(HrActivity::query()->where('action', 'requested')->count())->toBe(1)
            ->and(HrActivity::query()->where('action', 'requested')->first()?->meta)->not->toHaveKey('reason');

        $service->review($leave, LeaveStatus::Approved, $reviewer);

        expect(HrActivity::query()->where('action', LeaveStatus::Approved->value)->count())->toBe(1)
            ->and(HrActivity::query()->where('action', LeaveStatus::Approved->value)->first()?->actor_id)->toBe($reviewer->id);

        $pending = $service->store([
            'employee_id' => $employee->id,
            'type' => LeaveType::Unpaid->value,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
        ]);

        $service->cancel($pending);

        expect(HrActivity::query()->where('action', 'cancelled')->count())->toBe(1);
    });

    test('payroll run actions are recorded on process pay and cancel', function (): void {
        Event::fake([PayrollProcessed::class, PayrollPaid::class]);

        app(HrSettingsService::class)->update([
            'hr.payroll.approval_required' => false,
        ]);

        $actor = User::factory()->create();
        $payrollRun = PayrollRun::factory()->create([
            'status' => PayrollRunStatus::Draft,
        ]);

        $service = app(PayrollRunService::class);

        expect(fn () => $service->process($payrollRun, $actor))->toThrow(ValidationException::class);

        PayrollItem::factory()->for($payrollRun)->create();

        $processed = $service->process($payrollRun->fresh(), $actor);

        expect(HrActivity::query()->where('action', 'processed')->count())->toBe(1);

        $paid = $service->pay($processed, $actor, ['post_to_accounting' => false]);

        expect(HrActivity::query()->where('action', 'paid')->count())->toBe(1)
            ->and($paid->status)->toBe(PayrollRunStatus::Paid);

        $draft = PayrollRun::factory()->create([
            'status' => PayrollRunStatus::Draft,
        ]);

        $service->cancel($draft);

        expect(HrActivity::query()->where('action', 'cancelled')->count())->toBe(1);
    });
});

describe('HR plan limits', function (): void {
    beforeEach(function (): void {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            FeatureSeeder::class,
        ]);

        config([
            'tenancy.database.prefix' => 'testing_tenant_ats_',
            'tenancy.database.suffix' => '',
            'tenancy.bootstrappers' => array_values(array_filter(
                config('tenancy.bootstrappers'),
                static fn (string $bootstrapper): bool => ! str_contains($bootstrapper, 'CacheTenancyBootstrapper'),
            )),
        ]);
    });

    test('active job listing plan limit is enforced when subscribed', function (): void {
        $provisioned = provisionQualityTenant();
        subscribeTenantWithLimits($provisioned['tenant'], [
            'hr' => ['enabled' => true, 'limit' => null],
            'recruitment' => ['enabled' => true, 'limit' => null],
            'active_job_listings' => ['enabled' => true, 'limit' => 1],
        ]);

        $provisioned['tenant']->run(function (): void {
            $openings = app(JobOpeningService::class);
            $openings->publish($openings->store(['title' => 'First Role', 'slug' => 'first-role']));

            expect(fn () => $openings->publish($openings->store(['title' => 'Second Role', 'slug' => 'second-role'])))
                ->toThrow(ValidationException::class);
        });
    });

    test('applications per month plan limit is enforced when subscribed', function (): void {
        $provisioned = provisionQualityTenant();
        subscribeTenantWithLimits($provisioned['tenant'], [
            'hr' => ['enabled' => true, 'limit' => null],
            'recruitment' => ['enabled' => true, 'limit' => null],
            'applications_per_month' => ['enabled' => true, 'limit' => 1],
        ]);

        $provisioned['tenant']->run(function (): void {
            $openings = app(JobOpeningService::class);
            $published = $openings->publish($openings->store(['title' => 'Hiring Role', 'slug' => 'hiring-role']));
            $admin = User::query()->firstOrFail();
            $applications = app(JobApplicationService::class);

            $applications->store([
                'job_opening_id' => $published->id,
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.com',
            ], $admin);

            expect(fn () => $applications->store([
                'job_opening_id' => $published->id,
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'email' => 'grace@example.com',
            ], $admin))->toThrow(ValidationException::class);
        });
    });
});

describe('HR interview reminders', function (): void {
    beforeEach(function (): void {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        config([
            'tenancy.database.prefix' => 'testing_tenant_ats_',
            'tenancy.database.suffix' => '',
            'tenancy.bootstrappers' => array_values(array_filter(
                config('tenancy.bootstrappers'),
                static fn (string $bootstrapper): bool => ! str_contains($bootstrapper, 'CacheTenancyBootstrapper'),
            )),
        ]);
    });

    test('send interview reminders job marks reminders sent at configured hours', function (): void {
        Carbon::setTestNow('2026-08-20 10:00:00');

        $provisioned = provisionQualityTenant();
        $tenantId = $provisioned['tenant']->getTenantKey();

        $interviewId = $provisioned['tenant']->run(function (): int {
            app(HrSettingsService::class)->update([
                'hr.interviews.reminder_hours' => '24',
                'hr.notifications.recruitment' => true,
            ]);

            $openings = app(JobOpeningService::class);
            $published = $openings->publish($openings->store(['title' => 'Engineer', 'slug' => 'engineer']));
            $admin = User::query()->firstOrFail();

            $application = app(JobApplicationService::class)->store([
                'job_opening_id' => $published->id,
                'first_name' => 'Test',
                'last_name' => 'Candidate',
                'email' => 'candidate@example.com',
            ], $admin);

            $interview = app(InterviewService::class)->store([
                'job_application_id' => $application->id,
                'scheduled_at' => now()->addHours(24)->toDateTimeString(),
            ]);

            return $interview->id;
        });

        $notifier = Mockery::mock(RecruitmentNotifier::class);
        $notifier->shouldReceive('notifyStaff')
            ->once()
            ->with('hr.interview.reminder', Mockery::on(fn (array $payload): bool => ($payload['reminder_hours'] ?? null) === 24));
        $notifier->shouldReceive('notifyCandidate')->once();
        app()->instance(RecruitmentNotifier::class, $notifier);

        (new SendInterviewRemindersJob($tenantId))->handle(
            app(HrSettingsService::class),
            app(RecruitmentNotifier::class),
        );

        $provisioned['tenant']->run(function () use ($interviewId): void {
            $interview = Interview::query()->findOrFail($interviewId);

            expect($interview->reminders_sent)->toContain(24);
        });
    });
});

describe('HR notification toggles', function (): void {
    beforeEach(function (): void {
        runHrQualityMigrations();
    });

    test('leave requested notification respects hr notification toggle', function (): void {
        Event::fake([LeaveRequested::class, LeaveReviewed::class]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldNotReceive('send');
        app()->instance(NotificationService::class, $notifications);

        app(HrSettingsService::class)->update([
            'hr.notifications.leave' => false,
        ]);

        $employee = Employee::factory()->create();
        $leave = app(LeaveRequestService::class)->store([
            'employee_id' => $employee->id,
            'type' => LeaveType::Unpaid->value,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        app(SendLeaveRequestedNotification::class)->handle(new LeaveRequested($leave));
    });

    test('payroll paid notification respects hr notification toggle', function (): void {
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldNotReceive('send');
        app()->instance(NotificationService::class, $notifications);

        app(HrSettingsService::class)->update([
            'hr.notifications.payroll' => false,
        ]);

        $payrollRun = PayrollRun::factory()->create([
            'status' => PayrollRunStatus::Paid,
        ]);

        app(SendPayrollPaidNotification::class)->handle(new PayrollPaid($payrollRun));
    });
});
