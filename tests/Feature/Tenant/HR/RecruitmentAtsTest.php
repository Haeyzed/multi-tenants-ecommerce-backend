<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\ApplicationSource;
use App\Enums\Tenant\HR\InterviewRecommendation;
use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOfferStatus;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Events\CandidateHired;
use App\Events\InterviewScheduled;
use App\Events\JobApplicationReceived;
use App\Events\JobApplicationStageChanged;
use App\Events\JobOfferAccepted;
use App\Events\JobOfferSent;
use App\Models\Tenant\Candidate;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\CandidateService;
use App\Services\Tenant\HR\HiringService;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\InterviewService;
use App\Services\Tenant\HR\JobApplicationService;
use App\Services\Tenant\HR\JobOfferService;
use App\Services\Tenant\HR\JobOpeningService;
use App\Services\Tenant\HR\RecruitmentStageService;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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
        JobOfferSent::class,
        JobOfferAccepted::class,
        CandidateHired::class,
    ]);
});

test('job openings generate tenant-scoped slugs and hide unpublished listings from the public index', function (): void {
    $openings = app(JobOpeningService::class);

    $draft = $openings->store(['title' => 'Backend Engineer', 'status' => JobOpeningStatus::Draft]);
    $paused = $openings->store(['title' => 'Paused Role', 'status' => JobOpeningStatus::Open]);
    $openings->pause($paused);
    $closed = $openings->store(['title' => 'Closed Role', 'status' => JobOpeningStatus::Open]);
    $openings->close($closed);
    $live = $openings->publish($openings->store(['title' => 'Frontend Engineer', 'status' => JobOpeningStatus::Draft]));

    expect($draft->slug)->toBe('backend-engineer')
        ->and($live->status)->toBe(JobOpeningStatus::Open)
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
        'status' => JobOpeningStatus::Open,
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
        'status' => JobOpeningStatus::Open,
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
        ->and(app('router')->getRoutes()->getByName('tenant.public.jobs.index'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.public.jobs.apply'))->not->toBeNull()
        ->and(implode(',', app('router')->getRoutes()->getByName('tenant.public.jobs.apply')->gatherMiddleware()))->toContain('throttle:10,1');
});
