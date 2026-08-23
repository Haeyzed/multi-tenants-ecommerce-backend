<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\ApplicationSource;
use App\Enums\Tenant\HR\InterviewRecommendation;
use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOfferStatus;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Events\CandidateHired;
use App\Events\InterviewCompleted;
use App\Events\InterviewScheduled;
use App\Events\JobApplicationReceived;
use App\Events\JobApplicationStageChanged;
use App\Events\JobOfferAccepted;
use App\Events\JobOfferRejected;
use App\Events\JobOfferSent;
use App\Models\Tenant\HR\Candidate;
use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\HR\RecruitmentActivity;
use App\Models\Tenant\HR\WorkLocation;
use App\Models\Tenant\User;
use App\Policies\Tenant\CandidatePolicy;
use App\Services\Tenant\HR\CandidateService;
use App\Services\Tenant\HR\HiringService;
use App\Services\Tenant\HR\HrReportService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\InterviewService;
use App\Services\Tenant\HR\JobApplicationService;
use App\Services\Tenant\HR\JobOfferService;
use App\Services\Tenant\HR\JobOpeningService;
use App\Services\Tenant\HR\RecruitmentStageService;
use App\Services\Tenant\HR\WorkLocationService;
use App\Support\RecruitmentNotifier;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $migrations = [
        '2026_08_15_050007_create_seo_meta_table.php',
        '2026_08_15_060001_create_commerce_settings_table.php',
        '2026_08_16_161019_create_departments_table.php',
        '2026_08_16_161032_create_employees_table.php',
        '2026_08_17_211947_create_designations_table.php',
        '2026_08_17_211951_add_designation_id_to_employees_table.php',
        '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php',
        '2026_08_18_014822_create_employment_records_table.php',
        '2026_08_18_134113_create_overtime_policies_table.php',
        '2026_08_18_134108_create_work_schedules_table.php',
        '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php',
        '2026_08_18_142459_add_bank_and_tax_columns_to_employees_table.php',
        '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php',
        '2026_08_18_001426_create_employee_salaries_table.php',
        '2026_08_18_145943_create_job_openings_table.php',
        '2026_08_18_145945_create_job_applications_table.php',
        '2026_08_18_154933_create_candidates_table.php',
        '2026_08_18_154935_create_recruitment_stages_table.php',
        '2026_08_18_154937_add_ats_columns_to_job_openings_table.php',
        '2026_08_18_154939_add_candidate_and_stage_to_job_applications_table.php',
        '2026_08_18_154943_create_application_stage_histories_table.php',
        '2026_08_18_154948_create_interviews_table.php',
        '2026_08_18_154952_create_interview_interviewers_table.php',
        '2026_08_18_154954_create_interview_feedback_table.php',
        '2026_08_18_154957_create_job_offers_table.php',
        '2026_08_18_183538_create_work_locations_table.php',
        '2026_08_18_183540_add_work_location_id_to_employees_and_job_openings_tables.php',
        '2026_08_18_183542_add_response_token_hash_to_job_offers_table.php',
        '2026_08_18_183545_create_recruitment_activities_table.php',
        '2026_08_18_183548_migrate_job_opening_open_status_to_published.php',
        '2026_08_18_223849_create_interview_meetings_table.php',
        '2026_08_18_223854_create_interview_meeting_provider_settings_table.php',
        '2026_08_18_223902_add_timezone_and_reminders_to_interviews_table.php',
    ];

    foreach ($migrations as $file) {
        $table = match ($file) {
            '2026_08_15_050007_create_seo_meta_table.php' => 'seo_meta',
            '2026_08_15_060001_create_commerce_settings_table.php' => 'commerce_settings',
            '2026_08_16_161019_create_departments_table.php' => 'departments',
            '2026_08_16_161032_create_employees_table.php' => 'employees',
            '2026_08_17_211947_create_designations_table.php' => 'designations',
            '2026_08_18_014822_create_employment_records_table.php' => 'employment_records',
            '2026_08_18_134113_create_overtime_policies_table.php' => 'overtime_policies',
            '2026_08_18_134108_create_work_schedules_table.php' => 'work_schedules',
            '2026_08_18_001426_create_employee_salaries_table.php' => 'employee_salaries',
            '2026_08_18_145943_create_job_openings_table.php' => 'job_openings',
            '2026_08_18_145945_create_job_applications_table.php' => 'job_applications',
            '2026_08_18_154933_create_candidates_table.php' => 'candidates',
            '2026_08_18_154935_create_recruitment_stages_table.php' => 'recruitment_stages',
            '2026_08_18_154943_create_application_stage_histories_table.php' => 'application_stage_histories',
            '2026_08_18_154948_create_interviews_table.php' => 'interviews',
            '2026_08_18_154952_create_interview_interviewers_table.php' => 'interview_interviewers',
            '2026_08_18_154954_create_interview_feedback_table.php' => 'interview_feedback',
            '2026_08_18_154957_create_job_offers_table.php' => 'job_offers',
            '2026_08_18_183538_create_work_locations_table.php' => 'work_locations',
            '2026_08_18_183545_create_recruitment_activities_table.php' => 'recruitment_activities',
            '2026_08_18_223849_create_interview_meetings_table.php' => 'interview_meetings',
            '2026_08_18_223854_create_interview_meeting_provider_settings_table.php' => 'interview_meeting_provider_settings',
            default => null,
        };

        if ($table !== null && Schema::hasTable($table)) {
            continue;
        }

        if ($file === '2026_08_17_211951_add_designation_id_to_employees_table.php' && Schema::hasColumn('employees', 'designation_id')) {
            continue;
        }

        if ($file === '2026_08_18_003146_add_hr_profile_fields_to_employees_table.php' && Schema::hasColumn('employees', 'employment_type')) {
            continue;
        }

        if ($file === '2026_08_18_134125_add_work_schedule_and_overtime_rate_columns.php' && Schema::hasColumn('employees', 'work_schedule_id')) {
            continue;
        }

        if ($file === '2026_08_18_142459_add_bank_and_tax_columns_to_employees_table.php' && Schema::hasColumn('employees', 'bank_name')) {
            continue;
        }

        if ($file === '2026_08_18_145941_add_statutory_identifiers_and_payslip_columns.php' && Schema::hasColumn('employees', 'pension_pin')) {
            continue;
        }

        if ($file === '2026_08_18_154937_add_ats_columns_to_job_openings_table.php' && Schema::hasColumn('job_openings', 'slug')) {
            continue;
        }

        if ($file === '2026_08_18_154939_add_candidate_and_stage_to_job_applications_table.php' && Schema::hasColumn('job_applications', 'candidate_id')) {
            continue;
        }

        if ($file === '2026_08_18_183540_add_work_location_id_to_employees_and_job_openings_tables.php' && Schema::hasColumn('employees', 'work_location_id')) {
            continue;
        }

        if ($file === '2026_08_18_183542_add_response_token_hash_to_job_offers_table.php' && Schema::hasColumn('job_offers', 'response_token_hash')) {
            continue;
        }

        if ($file === '2026_08_18_223902_add_timezone_and_reminders_to_interviews_table.php' && Schema::hasColumn('interviews', 'timezone')) {
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

    Event::fake([
        JobApplicationReceived::class,
        JobApplicationStageChanged::class,
        InterviewScheduled::class,
        InterviewCompleted::class,
        JobOfferSent::class,
        JobOfferAccepted::class,
        JobOfferRejected::class,
        CandidateHired::class,
    ]);
});

test('job openings generate tenant-scoped slugs and hide unpublished listings from the public index', function (): void {
    $openings = app(JobOpeningService::class);

    $draft = $openings->store(['title' => 'Backend Engineer', 'status' => JobOpeningStatus::Draft]);
    $paused = $openings->store(['title' => 'Paused Role', 'status' => JobOpeningStatus::Published]);
    $openings->pause($paused);
    $closed = $openings->store(['title' => 'Closed Role', 'status' => JobOpeningStatus::Published]);
    $openings->close($closed);
    $live = $openings->publish($openings->store(['title' => 'Frontend Engineer', 'status' => JobOpeningStatus::Draft]));

    expect($draft->slug)->toBe('backend-engineer')
        ->and($live->status)->toBe(JobOpeningStatus::Published)
        ->and($live->published_at)->not->toBeNull();

    $public = $openings->listPublic();

    expect($public->total())->toBe(1)
        ->and($public->items()[0]->id)->toBe($live->id);

    expect(fn () => $openings->showPublicBySlug($draft->slug))->toThrow(ModelNotFoundException::class);
});

test('expired and paused job listings reject applications', function (): void {
    Carbon::setTestNow('2026-08-18 10:00:00');

    $openings = app(JobOpeningService::class);
    $applications = app(JobApplicationService::class);

    $expired = $openings->store([
        'title' => 'Expired Role',
        'status' => JobOpeningStatus::Published,
        'closes_at' => '2026-08-01',
    ]);

    expect(fn () => $applications->applyPublic([
        'job_opening_id' => $expired->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]))->toThrow(ValidationException::class);

    $paused = $openings->pause($openings->store([
        'title' => 'Paused Apply',
        'status' => JobOpeningStatus::Published,
    ]));

    expect(fn () => $applications->store([
        'job_opening_id' => $paused->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]))->toThrow(ValidationException::class);

    Carbon::setTestNow();
});

test('public applications create one candidate and reuse it across jobs', function (): void {
    Event::fake([JobApplicationReceived::class]);

    $openings = app(JobOpeningService::class);
    $applications = app(JobApplicationService::class);

    $backend = $openings->publish($openings->store(['title' => 'Backend Engineer']));
    $devops = $openings->publish($openings->store(['title' => 'DevOps Engineer']));

    $first = $applications->applyPublic([
        'job_opening_id' => $backend->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '08011112222',
        'source' => ApplicationSource::Website,
    ]);

    $second = $applications->applyPublic([
        'job_opening_id' => $devops->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '08011112222',
    ]);

    expect(Candidate::query()->count())->toBe(1)
        ->and($first->candidate_id)->toBe($second->candidate_id)
        ->and($first->status)->toBe(JobApplicationStatus::Received)
        ->and($first->stageHistory)->toHaveCount(1);

    expect(fn () => $applications->applyPublic([
        'job_opening_id' => $backend->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]))->toThrow(ValidationException::class);

    Event::assertDispatched(JobApplicationReceived::class);
});

test('application stage changes persist history', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'QA Engineer']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.com',
    ], $admin);

    $screening = app(RecruitmentStageService::class)->stageForKind(JobApplicationStatus::Screening);
    $moved = app(JobApplicationService::class)->moveStage($application, $screening, null, $admin, 'Phone screen passed.');

    expect($moved->status)->toBe(JobApplicationStatus::Screening)
        ->and($moved->recruitment_stage_id)->toBe($screening->id)
        ->and($moved->stageHistory()->count())->toBe(2);
});

test('interviews belong to applications and accept interviewer feedback', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    Employee::factory()->create(['user_id' => $admin->id]);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Engineer']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Linus',
        'last_name' => 'Torvalds',
        'email' => 'linus@example.com',
    ], $admin);

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $application->id,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'interviewer_ids' => [$admin->id],
    ]);

    $feedback = app(InterviewService::class)->submitFeedback($interview, $admin, [
        'rating' => 5,
        'recommendation' => InterviewRecommendation::StrongHire,
        'comments' => 'Excellent.',
    ]);

    $completed = app(InterviewService::class)->complete($interview);

    expect($interview->application->id)->toBe($application->id)
        ->and($feedback->rating)->toBe(5)
        ->and($completed->status)->toBe(InterviewStatus::Completed);
});

test('interviewers must have an active employee profile', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Staff Role']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'No',
        'last_name' => 'Profile',
        'email' => 'noprofile-interviewer@example.com',
    ], $admin);

    expect(fn () => app(InterviewService::class)->store([
        'job_application_id' => $application->id,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'interviewer_ids' => [$admin->id],
    ]))->toThrow(ValidationException::class);

    $terminated = User::factory()->create();
    $terminated->syncRoles(['admin']);
    Employee::factory()->terminated()->create(['user_id' => $terminated->id]);

    expect(fn () => app(InterviewService::class)->store([
        'job_application_id' => $application->id,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'interviewer_ids' => [$terminated->id],
    ]))->toThrow(ValidationException::class);
});

test('offers require approval when enabled and hiring converts a candidate once', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    app(HrSettingsService::class)->update([
        'hr.recruitment.offer_approval_required' => true,
        'hr.payroll.enabled' => false,
    ]);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Product Designer']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Katherine',
        'last_name' => 'Johnson',
        'email' => 'katherine@example.com',
    ], $admin);

    $offer = app(JobOfferService::class)->store([
        'job_application_id' => $application->id,
        'salary' => '450000.00',
        'currency' => 'NGN',
    ]);

    $pending = app(JobOfferService::class)->send($offer, $admin);
    expect($pending->status)->toBe(JobOfferStatus::PendingApproval);

    expect(fn () => app(HiringService::class)->convert($application->fresh(), $admin))->toThrow(ValidationException::class);

    app(JobOfferService::class)->approve($pending, $admin);
    $sent = app(JobOfferService::class)->send($pending->fresh(), $admin);
    $accepted = app(JobOfferService::class)->accept($sent, $admin);

    expect($accepted->status)->toBe(JobOfferStatus::Accepted);

    $employee = app(HiringService::class)->convert($application->fresh(['offers', 'candidate', 'jobOpening']), $admin);

    expect($employee)->toBeInstanceOf(Employee::class)
        ->and($employee->user->email)->toBe('katherine@example.com')
        ->and($employee->user->hasRole('employee'))->toBeTrue()
        ->and($employee->user->hasRole('admin'))->toBeFalse()
        ->and(Candidate::query()->where('employee_id', $employee->id)->count())->toBe(1)
        ->and($application->fresh()->status)->toBe(JobApplicationStatus::Hired)
        ->and(Employee::query()->count())->toBe(1);

    $again = app(HiringService::class)->convert($application->fresh(['offers', 'candidate']), $admin);
    expect($again->id)->toBe($employee->id)
        ->and(Employee::query()->count())->toBe(1);
});

test('interview can be required before an offer is created', function (): void {
    app(HrSettingsService::class)->update([
        'hr.recruitment.interview_required_before_offer' => true,
    ]);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Analyst']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Alan',
        'last_name' => 'Turing',
        'email' => 'alan@example.com',
    ]);

    expect(fn () => app(JobOfferService::class)->store([
        'job_application_id' => $application->id,
        'salary' => '200000.00',
    ]))->toThrow(ValidationException::class);
});

test('recruitment settings disable public listings and internal ATS access', function (): void {
    $openings = app(JobOpeningService::class);
    $openings->publish($openings->store(['title' => 'Visible Role']));

    app(HrSettingsService::class)->update([
        'hr.recruitment.public_listings_enabled' => false,
    ]);

    expect(fn () => app(JobOpeningService::class)->listPublic())->toThrow(ValidationException::class);

    app(HrSettingsService::class)->update([
        'hr.recruitment.enabled' => false,
        'hr.recruitment.public_listings_enabled' => true,
    ]);

    expect(fn () => app(JobOpeningService::class)->list())->toThrow(ValidationException::class)
        ->and(fn () => app(CandidateService::class)->list())->toThrow(ValidationException::class);
});

test('recruitment routes are registered for staff and public careers', function (): void {
    expect(app('router')->getRoutes()->getByName('tenant.candidates.index'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.job-applications.hire'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.job-offers.send'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.interviews.index'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.interviews.meeting.store'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.hr.interview-providers.index'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.candidates.activities'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.job-applications.activities'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.public.jobs.index'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.public.jobs.apply'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.public.offers.accept'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.work-locations.index'))->not->toBeNull()
        ->and(implode(',', app('router')->getRoutes()->getByName('tenant.public.jobs.apply')->gatherMiddleware()))->toContain('throttle:10,1');
});

test('work locations attach to job openings without duplicating department data', function (): void {
    $location = app(WorkLocationService::class)->store([
        'name' => 'Lekki Office',
        'code' => 'LK1',
        'address' => 'Lekki Phase 1',
    ]);

    $opening = app(JobOpeningService::class)->store([
        'title' => 'Office Manager',
        'work_location_id' => $location->id,
    ]);

    expect($opening->work_location_id)->toBe($location->id)
        ->and($opening->work_location)->toBe('Lekki Office')
        ->and(WorkLocation::query()->count())->toBe(1);
});

test('legacy open status is stored as published and candidates can accept offers by token', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $opening = app(JobOpeningService::class)->store([
        'title' => 'Support Lead',
        'status' => 'open',
    ]);

    expect($opening->status)->toBe(JobOpeningStatus::Published);

    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Ngozi',
        'last_name' => 'Okeke',
        'email' => 'ngozi@example.com',
    ], $admin);

    $offer = app(JobOfferService::class)->store([
        'job_application_id' => $application->id,
        'salary' => '300000.00',
        'currency' => 'NGN',
    ]);

    app(JobOfferService::class)->send($offer, $admin);

    $token = null;
    Event::assertDispatched(JobOfferSent::class, function (JobOfferSent $event) use (&$token): bool {
        $token = $event->publicToken;

        return is_string($event->publicToken) && $event->publicToken !== '';
    });

    $accepted = app(JobOfferService::class)->acceptPublicByToken((string) $token);

    expect($accepted->status)->toBe(JobOfferStatus::Accepted)
        ->and($accepted->response_token_hash)->toBeNull()
        ->and(RecruitmentActivity::query()->where('action', 'sent')->exists())->toBeTrue();

    expect(fn () => app(JobOfferService::class)->showPublicByToken((string) $token))
        ->toThrow(ValidationException::class);
});

test('hired employee role cannot view candidates', function (): void {
    $employeeUser = User::factory()->create();
    $employeeUser->syncRoles(['employee']);

    expect($employeeUser->can('hr.recruitment.view'))->toBeFalse()
        ->and((new CandidatePolicy)->viewAny($employeeUser))->toBeFalse();
});

test('staff notifications skip missing recruitment permissions', function (): void {
    Permission::query()->where('name', 'like', 'hr.recruitment.%')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => app(RecruitmentNotifier::class)->notifyStaff('hr.application.received', [
        'job_title' => 'Engineer',
        'candidate_name' => 'Ada Lovelace',
    ]))->not->toThrow(Throwable::class);
});

test('interviews can be listed by calendar window and opening', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    Employee::factory()->create(['user_id' => $admin->id]);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Calendar Role']));
    $other = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Other Role']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Ngozi',
        'last_name' => 'Okoro',
        'email' => 'ngozi@example.com',
    ], $admin);
    $otherApplication = app(JobApplicationService::class)->store([
        'job_opening_id' => $other->id,
        'first_name' => 'Chidi',
        'last_name' => 'Okeke',
        'email' => 'chidi@example.com',
    ], $admin);

    $inside = app(InterviewService::class)->store([
        'job_application_id' => $application->id,
        'scheduled_at' => '2026-08-20 10:00:00',
        'interviewer_ids' => [$admin->id],
    ]);
    app(InterviewService::class)->store([
        'job_application_id' => $otherApplication->id,
        'scheduled_at' => '2026-08-21 10:00:00',
    ]);
    app(InterviewService::class)->store([
        'job_application_id' => $application->id,
        'scheduled_at' => '2026-09-01 10:00:00',
    ]);

    $listed = app(InterviewService::class)->list([
        'from' => '2026-08-20',
        'to' => '2026-08-21',
        'job_opening_id' => $opening->id,
    ]);

    expect($listed->total())->toBe(1)
        ->and($listed->items()[0]->id)->toBe($inside->id)
        ->and($listed->items()[0]->application?->jobOpening?->title)->toBe('Calendar Role');
});

test('application and candidate activity feeds include pipeline events', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    Employee::factory()->create(['user_id' => $admin->id]);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Activity Role']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Funke',
        'last_name' => 'Adebayo',
        'email' => 'funke@example.com',
    ], $admin);

    app(InterviewService::class)->store([
        'job_application_id' => $application->id,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'interviewer_ids' => [$admin->id],
    ]);

    $applicationFeed = app(JobApplicationService::class)->listActivities($application);
    $candidateFeed = app(CandidateService::class)->listActivities($application->candidate);

    expect($applicationFeed->total())->toBeGreaterThanOrEqual(2)
        ->and($applicationFeed->pluck('action')->all())->toContain('received')
        ->and($applicationFeed->pluck('action')->all())->toContain('scheduled')
        ->and($candidateFeed->total())->toBeGreaterThanOrEqual(2);
});

test('offer emails include a public response url and recruitment reports expose funnel timing', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Funnel Role']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Tunde',
        'last_name' => 'Bakare',
        'email' => 'tunde@example.com',
    ], $admin);

    $screening = app(RecruitmentStageService::class)->stageForKind(JobApplicationStatus::Screening);
    app(JobApplicationService::class)->moveStage($application, $screening, null, $admin);

    $url = app(JobOfferService::class)->publicResponseUrl('offer-token-example');
    expect($url)->toContain('/api/public/offers/offer-token-example');

    $report = app(HrReportService::class)->recruitment([
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $received = collect($report['funnel'])->firstWhere('status', JobApplicationStatus::Received->value);
    $screeningRow = collect($report['funnel'])->firstWhere('status', JobApplicationStatus::Screening->value);

    expect($received['reached'])->toBe(1)
        ->and($received['advanced'])->toBe(1)
        ->and($screeningRow['reached'])->toBe(1)
        ->and($report['time_in_stage'])->not->toBeEmpty();
});
