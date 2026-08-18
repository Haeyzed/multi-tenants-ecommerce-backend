<?php

declare(strict_types=1);

use App\Enums\Tenant\HR\InterviewMeetingStatus;
use App\Enums\Tenant\HR\MeetingProvider;
use App\Events\InterviewCancelled;
use App\Events\InterviewCompleted;
use App\Events\InterviewMeetingCreated;
use App\Events\InterviewMeetingFailed;
use App\Events\InterviewRescheduled;
use App\Events\InterviewScheduled;
use App\Exceptions\Interview\InterviewMeetingProviderException;
use App\Exceptions\Interview\UnsupportedInterviewMeetingProviderException;
use App\Models\Tenant\Interview;
use App\Models\Tenant\InterviewMeeting;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\InterviewService;
use App\Services\Tenant\HR\JobApplicationService;
use App\Services\Tenant\HR\JobOpeningService;
use App\Services\Tenant\HR\Meetings\InterviewMeetingManager;
use App\Services\Tenant\HR\Meetings\InterviewMeetingProviderSettingService;
use App\Services\Tenant\HR\Meetings\InterviewMeetingService;
use App\Services\Tenant\HR\Meetings\Providers\FakeInterviewMeetingProvider;
use App\Services\Tenant\HR\Meetings\Providers\GoogleMeetInterviewMeetingProvider;
use App\Services\Tenant\HR\Meetings\Providers\ManualInterviewMeetingProvider;
use App\Services\Tenant\HR\Meetings\Providers\MicrosoftTeamsInterviewMeetingProvider;
use App\Services\Tenant\HR\Meetings\Providers\ZoomInterviewMeetingProvider;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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
        '2026_08_18_183545_create_recruitment_activities_table.php',
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
            '2026_08_18_145943_create_job_openings_table.php' => 'job_openings',
            '2026_08_18_145945_create_job_applications_table.php' => 'job_applications',
            '2026_08_18_154933_create_candidates_table.php' => 'candidates',
            '2026_08_18_154935_create_recruitment_stages_table.php' => 'recruitment_stages',
            '2026_08_18_154943_create_application_stage_histories_table.php' => 'application_stage_histories',
            '2026_08_18_154948_create_interviews_table.php' => 'interviews',
            '2026_08_18_154952_create_interview_interviewers_table.php' => 'interview_interviewers',
            '2026_08_18_154954_create_interview_feedback_table.php' => 'interview_feedback',
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

        if ($file === '2026_08_18_154937_add_ats_columns_to_job_openings_table.php' && Schema::hasColumn('job_openings', 'slug')) {
            continue;
        }

        if ($file === '2026_08_18_154939_add_candidate_and_stage_to_job_applications_table.php' && Schema::hasColumn('job_applications', 'candidate_id')) {
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

    FakeInterviewMeetingProvider::reset();

    Event::fake([
        InterviewScheduled::class,
        InterviewCompleted::class,
        InterviewCancelled::class,
        InterviewRescheduled::class,
        InterviewMeetingCreated::class,
        InterviewMeetingFailed::class,
    ]);
});

/**
 * @return array{admin: User, applicationId: int}
 */
function interviewMeetingApplication(): array
{
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);

    $opening = app(JobOpeningService::class)->publish(app(JobOpeningService::class)->store(['title' => 'Engineer']));
    $application = app(JobApplicationService::class)->store([
        'job_opening_id' => $opening->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada-meet@example.com',
    ], $admin);

    return [
        'admin' => $admin,
        'applicationId' => $application->id,
    ];
}

test('meeting manager resolves registered drivers without interview switches', function (): void {
    $manager = app(InterviewMeetingManager::class);

    expect($manager->driver('manual'))->toBeInstanceOf(ManualInterviewMeetingProvider::class)
        ->and($manager->driver('fake'))->toBeInstanceOf(FakeInterviewMeetingProvider::class)
        ->and($manager->driver('zoom'))->toBeInstanceOf(ZoomInterviewMeetingProvider::class)
        ->and($manager->driver('google_meet'))->toBeInstanceOf(GoogleMeetInterviewMeetingProvider::class)
        ->and($manager->driver('microsoft_teams'))->toBeInstanceOf(MicrosoftTeamsInterviewMeetingProvider::class)
        ->and(config('interview_meetings.default'))->toBe('manual')
        ->and(app(HrSettingsService::class)->defaultInterviewMeetingProvider())->toBe('manual');

    expect(fn () => $manager->driver('webex'))->toThrow(UnsupportedInterviewMeetingProviderException::class);
});

test('manual provider stores a supplied meeting url and skips external apis', function (): void {
    Http::preventStrayRequests();

    $setup = interviewMeetingApplication();

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'timezone' => 'Africa/Lagos',
        'meeting_provider' => MeetingProvider::Manual->value,
        'meeting_url' => 'https://meet.example.com/abc',
        'meeting_password' => 'room-1',
        'interviewer_ids' => [$setup['admin']->id],
    ]);

    $meeting = $interview->currentMeeting;

    expect($meeting)->not->toBeNull()
        ->and($meeting->provider)->toBe(MeetingProvider::Manual)
        ->and($meeting->join_url)->toBe('https://meet.example.com/abc')
        ->and($meeting->password)->toBe('room-1')
        ->and($meeting->status)->toBe(InterviewMeetingStatus::Created)
        ->and($interview->meeting_url)->toBe('https://meet.example.com/abc')
        ->and($interview->timezone)->toBe('Africa/Lagos');
});

test('in person interviews do not invent a manual meeting', function (): void {
    $setup = interviewMeetingApplication();

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'location' => 'Lagos office',
        'interviewer_ids' => [$setup['admin']->id],
    ]);

    expect($interview->currentMeeting)->toBeNull()
        ->and($interview->meeting_url)->toBeNull()
        ->and(InterviewMeeting::query()->count())->toBe(0);
});

test('fake provider can create update cancel and recreate meetings', function (): void {
    $setup = interviewMeetingApplication();

    app(HrSettingsService::class)->update([
        'hr.interviews.default_provider' => 'fake',
        'hr.interviews.auto_create_meeting' => true,
    ]);

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'duration_minutes' => 45,
        'interviewer_ids' => [$setup['admin']->id],
    ]);

    $first = $interview->currentMeeting;

    expect($first)->not->toBeNull()
        ->and($first->provider)->toBe(MeetingProvider::Fake)
        ->and($first->join_url)->toStartWith('https://meet.example.test/')
        ->and($first->host_url)->not->toBeNull();

    $updated = app(InterviewMeetingService::class)->updateForInterview($interview->fresh(['currentMeeting']));
    expect($updated->id)->toBe($first->id);

    $recreated = app(InterviewMeetingService::class)->recreateForInterview($interview->fresh(['currentMeeting']));

    expect($recreated->id)->not->toBe($first->id)
        ->and($first->fresh()->is_current)->toBeFalse()
        ->and($first->fresh()->status)->toBe(InterviewMeetingStatus::Superseded)
        ->and(InterviewMeeting::query()->where('interview_id', $interview->id)->count())->toBe(2);

    app(InterviewService::class)->cancel($interview->fresh(['currentMeeting']));

    expect($recreated->fresh()->status)->toBe(InterviewMeetingStatus::Cancelled)
        ->and(FakeInterviewMeetingProvider::meetings())->toBe([]);
});

test('provider failure does not leave a successful meeting or interview', function (): void {
    $setup = interviewMeetingApplication();

    app(HrSettingsService::class)->update([
        'hr.interviews.default_provider' => 'fake',
        'hr.interviews.auto_create_meeting' => true,
    ]);

    FakeInterviewMeetingProvider::$failNext = true;

    expect(fn () => app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]))->toThrow(InterviewMeetingProviderException::class);

    expect(Interview::query()->count())->toBe(0)
        ->and(InterviewMeeting::query()->where('status', InterviewMeetingStatus::Created)->count())->toBe(0);
});

test('zoom provider creates updates and cancels through the official api', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://zoom.us/oauth/token' => Http::response(['access_token' => 'zoom-token'], 200),
        'https://api.zoom.us/v2/users/*/meetings' => Http::response([
            'id' => 987654,
            'join_url' => 'https://zoom.us/j/987654',
            'start_url' => 'https://zoom.us/s/987654',
            'password' => 'secret-room',
            'start_time' => '2026-08-20T10:00:00Z',
            'duration' => 60,
        ], 201),
        'https://api.zoom.us/v2/meetings/*' => Http::response([
            'id' => 987654,
            'join_url' => 'https://zoom.us/j/987654',
            'start_url' => 'https://zoom.us/s/987654',
            'password' => 'secret-room',
            'start_time' => '2026-08-20T11:00:00Z',
            'duration' => 90,
        ], 200),
    ]);

    $setup = interviewMeetingApplication();
    $providers = app(InterviewMeetingProviderSettingService::class);
    $public = $providers->upsert('zoom', [
        'enabled' => true,
        'credentials' => [
            'account_id' => 'acc',
            'client_id' => 'cid',
            'client_secret' => 'zoom-client-secret',
            'host_user_id' => 'host@example.com',
        ],
    ]);

    expect($public['configured'])->toBeTrue()
        ->and($public)->not->toHaveKey('credentials')
        ->and(json_encode($public))->not->toContain('zoom-client-secret');

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'meeting_provider' => 'zoom',
        'interviewer_ids' => [$setup['admin']->id],
    ]);

    expect($interview->currentMeeting?->external_id)->toBe('987654')
        ->and($interview->meeting_url)->toBe('https://zoom.us/j/987654')
        ->and($interview->currentMeeting?->host_url)->toBe('https://zoom.us/s/987654');

    $interview->duration_minutes = 90;
    $interview->save();
    app(InterviewMeetingService::class)->updateForInterview($interview->fresh(['currentMeeting']));

    app(InterviewMeetingService::class)->cancelForInterview($interview->fresh(['currentMeeting']), force: true);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://zoom.us/oauth/token'
        && $request->data()['grant_type'] === 'account_credentials');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/users/host%40example.com/meetings'));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/meetings/987654'));
});

test('zoom rejects incomplete credentials without calling the api', function (): void {
    Http::preventStrayRequests();

    $setup = interviewMeetingApplication();
    app(InterviewMeetingProviderSettingService::class)->upsert('zoom', [
        'enabled' => true,
        'credentials' => ['client_id' => 'cid'],
    ]);

    expect(fn () => app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'meeting_provider' => 'zoom',
    ]))->toThrow(ValidationException::class);

    expect(Interview::query()->count())->toBe(0);
});

test('google meet provider creates a calendar conference through oauth', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-token'], 200),
        'https://www.googleapis.com/calendar/v3/*' => Http::response([
            'id' => 'event-1',
            'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
            'conferenceData' => [
                'entryPoints' => [
                    ['entryPointType' => 'video', 'uri' => 'https://meet.google.com/abc-defg-hij'],
                ],
            ],
            'start' => ['dateTime' => '2026-08-20T10:00:00+00:00'],
            'end' => ['dateTime' => '2026-08-20T11:00:00+00:00'],
        ], 200),
    ]);

    $setup = interviewMeetingApplication();
    app(InterviewMeetingProviderSettingService::class)->upsert('google_meet', [
        'enabled' => true,
        'credentials' => [
            'client_id' => 'gid',
            'client_secret' => 'gsecret',
            'refresh_token' => 'refresh-me',
            'calendar_id' => 'primary',
        ],
    ]);

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'meeting_provider' => 'google_meet',
    ]);

    expect($interview->currentMeeting?->join_url)->toBe('https://meet.google.com/abc-defg-hij')
        ->and($interview->currentMeeting?->external_id)->toBe('event-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request->data()['grant_type'] === 'refresh_token');
});

test('microsoft teams provider creates an online meeting through graph', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response(['access_token' => 'graph-token'], 200),
        'https://graph.microsoft.com/v1.0/*' => Http::response([
            'id' => 'teams-meeting-1',
            'joinWebUrl' => 'https://teams.microsoft.com/l/meetup-join/1',
            'startDateTime' => '2026-08-20T10:00:00+00:00',
            'endDateTime' => '2026-08-20T11:00:00+00:00',
        ], 201),
    ]);

    $setup = interviewMeetingApplication();
    app(InterviewMeetingProviderSettingService::class)->upsert('microsoft_teams', [
        'enabled' => true,
        'credentials' => [
            'tenant_id' => 'ms-tenant',
            'client_id' => 'ms-id',
            'client_secret' => 'ms-secret',
            'user_id' => 'organizer-id',
        ],
    ]);

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'meeting_provider' => 'microsoft_teams',
    ]);

    expect($interview->currentMeeting?->external_id)->toBe('teams-meeting-1')
        ->and($interview->meeting_url)->toBe('https://teams.microsoft.com/l/meetup-join/1');
});

test('provider connection tests authenticate without creating a meeting', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://zoom.us/oauth/token' => Http::response(['access_token' => 'zoom-token'], 200),
    ]);

    app(InterviewMeetingProviderSettingService::class)->upsert('zoom', [
        'enabled' => true,
        'credentials' => [
            'account_id' => 'acc',
            'client_id' => 'cid',
            'client_secret' => 'secret',
            'host_user_id' => 'host@example.com',
        ],
    ]);

    $result = app(InterviewMeetingProviderSettingService::class)->test('zoom');

    expect($result['ok'])->toBeTrue()
        ->and(InterviewMeeting::query()->count())->toBe(0);

    Http::assertSentCount(1);
});

test('unsupported meeting update is rejected by capabilities', function (): void {
    $setup = interviewMeetingApplication();
    FakeInterviewMeetingProvider::$rejectUpdate = true;

    app(HrSettingsService::class)->update([
        'hr.interviews.default_provider' => 'fake',
    ]);

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    expect(fn () => app(InterviewMeetingService::class)->updateForInterview($interview->fresh(['currentMeeting'])))
        ->toThrow(InterviewMeetingProviderException::class);
});

test('hr interview settings change default provider and duration', function (): void {
    app(HrSettingsService::class)->update([
        'hr.interviews.default_provider' => 'manual',
        'hr.interviews.default_duration_minutes' => 30,
        'hr.interviews.online_enabled' => true,
        'hr.interviews.auto_create_meeting' => false,
    ]);

    $settings = app(HrSettingsService::class);

    expect($settings->defaultInterviewMeetingProvider())->toBe('manual')
        ->and($settings->defaultInterviewDurationMinutes())->toBe(30)
        ->and($settings->autoCreateInterviewMeeting())->toBeFalse()
        ->and($settings->interviewReminderHours())->toBe([1, 24]);
});

test('candidate notification payload omits host urls', function (): void {
    $setup = interviewMeetingApplication();

    app(HrSettingsService::class)->update([
        'hr.interviews.default_provider' => 'fake',
    ]);

    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $candidate = $interview->recruitmentNotificationPayload(includeHostUrl: false);
    $staff = $interview->recruitmentNotificationPayload(includeHostUrl: true);

    expect($candidate)->not->toHaveKey('meeting_host_url')
        ->and($candidate['meeting_join_url'])->not->toBeNull()
        ->and($staff['meeting_host_url'])->toStartWith('https://meet.example.test/host/');
});

test('only recruitment managers may update interview meetings', function (): void {
    $setup = interviewMeetingApplication();
    $interview = app(InterviewService::class)->store([
        'job_application_id' => $setup['applicationId'],
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'meeting_url' => 'https://meet.example.com/room',
        'interviewer_ids' => [$setup['admin']->id],
    ]);

    $customer = User::factory()->create();
    $customer->syncRoles(['customer']);

    expect($setup['admin']->can('update', $interview))->toBeTrue()
        ->and($customer->can('update', $interview))->toBeFalse()
        ->and($customer->can('hr.settings.update'))->toBeFalse()
        ->and($setup['admin']->can('hr.settings.update'))->toBeTrue();
});

test('meeting provider routes are registered', function (): void {
    expect(app('router')->getRoutes()->getByName('tenant.hr.interview-providers.index'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.hr.interview-providers.test'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.interviews.meeting.store'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('tenant.interviews.meeting.destroy'))->not->toBeNull();
});
