<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR\Meetings;

use App\Contracts\Interview\InterviewMeetingProvider;
use App\DTO\Interview\MeetingRequest;
use App\DTO\Interview\MeetingResult;
use App\Enums\Tenant\HR\InterviewMeetingStatus;
use App\Enums\Tenant\HR\MeetingProvider;
use App\Events\InterviewMeetingCreated;
use App\Events\InterviewMeetingFailed;
use App\Exceptions\Interview\InterviewMeetingProviderException;
use App\Models\Tenant\HR\Interview;
use App\Models\Tenant\HR\InterviewMeeting;
use App\Services\Tenant\HR\HrSettingsService;
use App\Services\Tenant\HR\RecruitmentActivityService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Interview meeting lifecycle. Providers are resolved through InterviewMeetingManager.
 */
class InterviewMeetingService
{
    /**
     * Create a new class instance.
     *
     * @param  InterviewMeetingManager  $manager
     * @param  InterviewMeetingProviderSettingService  $providerSettings
     * @param  HrSettingsService  $hrSettings
     * @param  RecruitmentActivityService  $activities
     */
    public function __construct(
        private readonly InterviewMeetingManager $manager,
        private readonly InterviewMeetingProviderSettingService $providerSettings,
        private readonly HrSettingsService $hrSettings,
        private readonly RecruitmentActivityService $activities,
    ) {}

    /**
     * Create for interview.
     *
     * @param  Interview  $interview
     * @param  array<string, mixed>  $input
     * @param  bool  $recreate
     * @return InterviewMeeting
     */
    public function createForInterview(Interview $interview, array $input = [], bool $recreate = false): InterviewMeeting
    {
        $this->assertTables();

        $providerName = $this->resolveProviderName($interview, $input);
        $driver = $this->manager->driver($providerName);
        $this->assertProviderUsable($driver, $providerName);

        if ($recreate) {
            $this->supersedeCurrent($interview, cancelExternal: true);
        } elseif ($interview->currentMeeting !== null) {
            throw ValidationException::withMessages([
                'meeting' => ['This interview already has a meeting. Recreate it instead of creating another.'],
            ]);
        }

        $request = $this->requestFor($interview, $driver, $input);

        if ($providerName === MeetingProvider::Manual->value && ($request->joinUrl === null || $request->joinUrl === '')) {
            throw ValidationException::withMessages([
                'meeting_url' => ['A meeting URL is required when using the manual meeting provider.'],
            ]);
        }

        try {
            $result = $driver->createMeeting($request);
        } catch (InterviewMeetingProviderException $exception) {
            $this->recordFailure($interview, $providerName, $exception);
            throw $exception;
        }

        $meeting = $this->persistResult($interview, $result);
        $this->syncInterviewSnapshot($interview, $meeting);

        $this->activities->record($interview, 'meeting_created', null, [
            'provider' => $meeting->provider->value,
            'external_id' => $meeting->external_id,
            'status' => $meeting->status->value,
        ]);

        Log::info('Interview meeting created', [
            'provider' => $meeting->provider->value,
            'operation' => 'create',
            'interview_id' => $interview->id,
            'external_id' => $meeting->external_id,
            'tenant_id' => tenant()?->getTenantKey(),
            'success' => true,
        ]);

        event(new InterviewMeetingCreated($interview, $meeting));

        return $meeting;
    }

    /**
     * Maybe create for interview.
     *
     * @param  Interview  $interview
     * @param  array<string, mixed>  $input
     * @return ?InterviewMeeting
     */
    public function maybeCreateForInterview(Interview $interview, array $input = []): ?InterviewMeeting
    {
        if (! $this->hrSettings->onlineInterviewsEnabled() || ! $this->hrSettings->autoCreateInterviewMeeting()) {
            return null;
        }

        $providerName = $this->resolveProviderName($interview, $input);

        if ($providerName === MeetingProvider::Manual->value) {
            $url = $input['meeting_url'] ?? $input['join_url'] ?? $interview->meeting_url;

            if (! is_string($url) || $url === '') {
                return null;
            }
        }

        return $this->createForInterview($interview, $input);
    }

    /**
     * Update for interview.
     *
     * @param  Interview  $interview
     * @param  array<string, mixed>  $input
     * @return InterviewMeeting
     */
    public function updateForInterview(Interview $interview, array $input = []): InterviewMeeting
    {
        $this->assertTables();

        $meeting = $interview->currentMeeting;

        if ($meeting === null) {
            throw ValidationException::withMessages([
                'meeting' => ['This interview does not have a current meeting.'],
            ]);
        }

        $driver = $this->manager->driver($meeting->provider->value);

        if (! $driver->capabilities()->canUpdate) {
            throw ValidationException::withMessages([
                'meeting' => ['The configured meeting provider cannot update meetings. Recreate the meeting instead.'],
            ]);
        }

        $request = $this->requestFor($interview, $driver, $input, $meeting);

        try {
            $result = $driver->updateMeeting($request);
        } catch (InterviewMeetingProviderException $exception) {
            Log::warning('Interview meeting update failed', [
                'provider' => $meeting->provider->value,
                'operation' => 'update',
                'interview_id' => $interview->id,
                'external_id' => $meeting->external_id,
                'tenant_id' => tenant()?->getTenantKey(),
                'success' => false,
            ]);

            throw $exception;
        }

        $meeting->fill($this->attributesFromResult($result));
        $meeting->save();
        $this->syncInterviewSnapshot($interview, $meeting);

        $this->activities->record($interview, 'meeting_updated', null, [
            'provider' => $meeting->provider->value,
            'external_id' => $meeting->external_id,
        ]);

        Log::info('Interview meeting updated', [
            'provider' => $meeting->provider->value,
            'operation' => 'update',
            'interview_id' => $interview->id,
            'external_id' => $meeting->external_id,
            'tenant_id' => tenant()?->getTenantKey(),
            'success' => true,
        ]);

        return $meeting->fresh() ?? $meeting;
    }

    /**
     * Sync schedule.
     *
     * @param  Interview  $interview
     * @return void
     */
    public function syncSchedule(Interview $interview): void
    {
        if (! $this->hrSettings->autoSyncInterviewMeeting()) {
            return;
        }

        $meeting = $interview->currentMeeting;

        if ($meeting === null || $meeting->status !== InterviewMeetingStatus::Created) {
            return;
        }

        $this->updateForInterview($interview);
    }

    /**
     * Cancel for interview.
     *
     * @param  Interview  $interview
     * @param  bool  $force
     * @return void
     */
    public function cancelForInterview(Interview $interview, bool $force = false): void
    {
        if (! Schema::hasTable('interview_meetings')) {
            return;
        }

        $meeting = $interview->currentMeeting;

        if ($meeting === null) {
            return;
        }

        if (! $force && ! $this->hrSettings->cancelExternalInterviewMeeting()) {
            return;
        }

        $driver = $this->manager->driver($meeting->provider->value);

        if ($meeting->provider === MeetingProvider::Manual && ! $force) {
            $this->markCancelled($meeting);

            return;
        }

        if ($driver->capabilities()->canCancel && $meeting->external_id !== null) {
            try {
                $driver->cancelMeeting($this->requestFor($interview, $driver, [], $meeting));
            } catch (InterviewMeetingProviderException $exception) {
                Log::warning('Interview meeting cancellation failed', [
                    'provider' => $meeting->provider->value,
                    'operation' => 'cancel',
                    'interview_id' => $interview->id,
                    'external_id' => $meeting->external_id,
                    'tenant_id' => tenant()?->getTenantKey(),
                    'success' => false,
                ]);

                if ($force) {
                    throw $exception;
                }
            }
        }

        $this->markCancelled($meeting);
        $interview->meeting_url = null;
        $interview->save();
    }

    /**
     * Recreate for interview.
     *
     * @param  Interview  $interview
     * @param  array<string, mixed>  $input
     * @return InterviewMeeting
     */
    public function recreateForInterview(Interview $interview, array $input = []): InterviewMeeting
    {
        return $this->createForInterview($interview, $input, recreate: true);
    }

    /**
     * Resolve provider name.
     *
     * @param  Interview  $interview
     * @param  array<string, mixed>  $input
     * @return string
     */
    public function resolveProviderName(Interview $interview, array $input = []): string
    {
        $requested = $input['meeting_provider'] ?? $input['provider'] ?? null;

        if (is_string($requested) && $requested !== '') {
            return $requested;
        }

        $current = $interview->currentMeeting?->provider?->value;

        if (is_string($current) && $current !== '') {
            return $current;
        }

        return $this->hrSettings->defaultInterviewMeetingProvider();
    }

    /**
     * Request for.
     *
     * @param  Interview  $interview
     * @param  InterviewMeetingProvider  $driver
     * @param  array<string, mixed>  $input
     * @param  ?InterviewMeeting  $meeting
     * @return MeetingRequest
     */
    protected function requestFor(
        Interview $interview,
        InterviewMeetingProvider $driver,
        array $input = [],
        ?InterviewMeeting $meeting = null,
    ): MeetingRequest {
        $interview->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $interview->application;
        $candidate = trim(($application?->first_name ?? '').' '.($application?->last_name ?? ''));
        $jobTitle = $application?->jobOpening?->title ?? 'Interview';
        $timezone = $interview->timezone ?: $this->hrSettings->interviewTimezone();
        $duration = (int) ($interview->duration_minutes ?: $this->hrSettings->defaultInterviewDurationMinutes());

        return new MeetingRequest(
            topic: trim($jobTitle.' interview'.($candidate !== '' ? ' — '.$candidate : '')),
            startsAt: $interview->scheduled_at,
            durationMinutes: max(5, $duration),
            timezone: $timezone,
            joinUrl: $this->stringOrNull($input['meeting_url'] ?? $input['join_url'] ?? $meeting?->join_url ?? $interview->meeting_url),
            password: $this->stringOrNull($input['meeting_password'] ?? $meeting?->password),
            externalId: $meeting?->external_id,
            credentials: $this->providerSettings->credentialsFor($driver->name()),
            interviewId: $interview->id,
            agenda: $this->stringOrNull($interview->notes),
        );
    }

    /**
     * Persist result.
     *
     * @param  Interview  $interview
     * @param  MeetingResult  $result
     * @return InterviewMeeting
     */
    protected function persistResult(Interview $interview, MeetingResult $result): InterviewMeeting
    {
        return InterviewMeeting::query()->create([
            'interview_id' => $interview->id,
            ...$this->attributesFromResult($result),
            'is_current' => true,
            'failure_reason' => null,
        ]);
    }

    /**
     * Attributes from result.
     *
     * @param  MeetingResult  $result
     * @return array<string, mixed>
     */
    protected function attributesFromResult(MeetingResult $result): array
    {
        return [
            'provider' => $result->provider,
            'external_id' => $result->externalId,
            'join_url' => $result->joinUrl,
            'host_url' => $result->hostUrl,
            'password' => $result->password,
            'starts_at' => $result->startsAt,
            'ends_at' => $result->endsAt,
            'status' => $result->status,
        ];
    }

    /**
     * Sync interview snapshot.
     *
     * @param  Interview  $interview
     * @param  InterviewMeeting  $meeting
     * @return void
     */
    protected function syncInterviewSnapshot(Interview $interview, InterviewMeeting $meeting): void
    {
        $interview->meeting_url = $meeting->join_url;
        $interview->save();
    }

    /**
     * Supersede current.
     *
     * @param  Interview  $interview
     * @param  bool  $cancelExternal
     * @return void
     */
    protected function supersedeCurrent(Interview $interview, bool $cancelExternal): void
    {
        $current = $interview->currentMeeting;

        if ($current === null) {
            return;
        }

        if ($cancelExternal) {
            $driver = $this->manager->driver($current->provider->value);

            if ($driver->capabilities()->canCancel && $current->external_id !== null && $current->provider !== MeetingProvider::Manual) {
                try {
                    $driver->cancelMeeting($this->requestFor($interview, $driver, [], $current));
                } catch (InterviewMeetingProviderException) {
                    Log::warning('Interview meeting supersede cancel failed', [
                        'provider' => $current->provider->value,
                        'operation' => 'cancel',
                        'interview_id' => $interview->id,
                        'external_id' => $current->external_id,
                        'tenant_id' => tenant()?->getTenantKey(),
                        'success' => false,
                    ]);
                }
            }
        }

        $current->is_current = false;
        $current->status = InterviewMeetingStatus::Superseded;
        $current->save();
        $interview->unsetRelation('currentMeeting');
    }

    /**
     * Mark cancelled.
     *
     * @param  InterviewMeeting  $meeting
     * @return void
     */
    protected function markCancelled(InterviewMeeting $meeting): void
    {
        $meeting->status = InterviewMeetingStatus::Cancelled;
        $meeting->is_current = false;
        $meeting->save();

        $this->activities->record($meeting->interview, 'meeting_cancelled', null, [
            'provider' => $meeting->provider->value,
            'external_id' => $meeting->external_id,
        ]);
    }

    /**
     * Record failure.
     *
     * @param  Interview  $interview
     * @param  string  $provider
     * @param  InterviewMeetingProviderException  $exception
     * @return void
     */
    protected function recordFailure(Interview $interview, string $provider, InterviewMeetingProviderException $exception): void
    {
        InterviewMeeting::query()->create([
            'interview_id' => $interview->id,
            'provider' => $provider,
            'status' => InterviewMeetingStatus::Failed,
            'is_current' => false,
            'failure_reason' => $exception->getMessage(),
        ]);

        $this->activities->record($interview, 'meeting_failed', null, [
            'provider' => $provider,
            'status' => InterviewMeetingStatus::Failed->value,
        ]);

        Log::warning('Interview meeting creation failed', [
            'provider' => $provider,
            'operation' => 'create',
            'interview_id' => $interview->id,
            'tenant_id' => tenant()?->getTenantKey(),
            'success' => false,
        ]);

        event(new InterviewMeetingFailed($interview, $provider, $exception->getMessage()));
    }

    /**
     * Assert provider usable.
     *
     * @param  InterviewMeetingProvider  $driver
     * @param  string  $providerName
     * @return void
     */
    protected function assertProviderUsable(InterviewMeetingProvider $driver, string $providerName): void
    {
        if (! $this->hrSettings->onlineInterviewsEnabled() && $driver->capabilities()->requiresExternalApi) {
            throw ValidationException::withMessages([
                'meeting_provider' => ['Online interviews are disabled in HR settings.'],
            ]);
        }

        if ($driver->capabilities()->requiresExternalApi && ! $this->providerSettings->isEnabled($providerName)) {
            throw ValidationException::withMessages([
                'meeting_provider' => ['This meeting provider is not enabled for the tenant.'],
            ]);
        }

        $credentials = $this->providerSettings->credentialsFor($providerName);

        if ($driver->capabilities()->requiresExternalApi && ! $driver->isConfigured($credentials)) {
            throw ValidationException::withMessages([
                'meeting_provider' => ['This meeting provider is not configured. Add credentials in HR interview provider settings.'],
            ]);
        }
    }

    /**
     * Assert tables.
     *
     * @return void
     */
    protected function assertTables(): void
    {
        if (! Schema::hasTable('interview_meetings')) {
            throw ValidationException::withMessages([
                'meeting' => ['Interview meetings are not available.'],
            ]);
        }
    }

    /**
     * String or null.
     *
     * @param  mixed  $value
     * @return ?string
     */
    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
