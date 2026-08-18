<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\InterviewRecommendation;
use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\InterviewType;
use App\Events\InterviewCancelled;
use App\Events\InterviewCompleted;
use App\Events\InterviewRescheduled;
use App\Events\InterviewScheduled;
use App\Exceptions\Interview\InterviewMeetingProviderException;
use App\Models\Tenant\Interview;
use App\Models\Tenant\InterviewFeedback;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\Meetings\InterviewMeetingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Interviews belong to applications, not to a separate candidate calendar.
 */
class InterviewService
{
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly RecruitmentActivityService $activities,
        private readonly InterviewMeetingService $meetings,
    ) {}

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     status?: string|null,
     *     job_application_id?: int|null,
     *     job_opening_id?: int|null,
     *     interviewer_id?: int|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Interview>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return Interview::query()
            ->with(['application.candidate', 'application.jobOpening', 'interviewers', 'currentMeeting'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Interview
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $application = JobApplication::query()->findOrFail($data['job_application_id']);
        $this->assertApplicationOpen($application);

        $meetingInput = $this->extractMeetingInput($data);
        unset($data['meeting_provider'], $data['meeting_password'], $data['join_url']);

        $interview = Interview::query()->create([
            'job_application_id' => $application->id,
            'interview_type' => $data['interview_type'] ?? InterviewType::Other,
            'scheduled_at' => $data['scheduled_at'],
            'timezone' => $data['timezone'] ?? $this->hrSettings->interviewTimezone(),
            'duration_minutes' => $data['duration_minutes'] ?? $this->hrSettings->defaultInterviewDurationMinutes(),
            'location' => $data['location'] ?? null,
            'meeting_url' => $data['meeting_url'] ?? null,
            'status' => $data['status'] ?? InterviewStatus::Scheduled,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncInterviewers($interview, $data['interviewer_ids'] ?? []);

        try {
            $this->meetings->maybeCreateForInterview($interview, $meetingInput);
        } catch (InterviewMeetingProviderException|ValidationException $exception) {
            $interview->delete();

            throw $exception;
        }

        $interview = $interview->fresh(['application.candidate', 'application.jobOpening', 'interviewers', 'feedback', 'currentMeeting']) ?? $interview;

        $this->activities->record($interview, 'scheduled', null, [
            'job_application_id' => $application->id,
            'status' => $interview->status->value,
            'meeting_provider' => $interview->currentMeeting?->provider?->value,
        ]);

        event(new InterviewScheduled($interview));

        return $interview;
    }

    public function show(Interview $interview): Interview
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $interview->load(['application.candidate', 'application.jobOpening', 'interviewers', 'feedback.interviewer', 'currentMeeting']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Interview $interview, array $data): Interview
    {
        $this->hrSettings->assertRecruitmentEnabled();

        unset($data['job_application_id']);

        $interviewerIds = $data['interviewer_ids'] ?? null;
        unset($data['interviewer_ids']);

        $meetingInput = $this->extractMeetingInput($data);
        unset($data['meeting_provider'], $data['meeting_password'], $data['join_url']);

        $previousStatus = $interview->status;
        $previousScheduledAt = $interview->scheduled_at?->toDateTimeString();
        $previousDuration = $interview->duration_minutes;

        if (isset($data['scheduled_at']) && $interview->status === InterviewStatus::Scheduled) {
            $data['status'] = $data['status'] ?? InterviewStatus::Rescheduled;
        }

        $interview->fill($data);
        $interview->save();

        if (is_array($interviewerIds)) {
            $this->syncInterviewers($interview, $interviewerIds);
        }

        $fresh = $interview->fresh(['application.candidate', 'application.jobOpening', 'interviewers', 'feedback', 'currentMeeting']) ?? $interview;

        if ($fresh->status === InterviewStatus::Cancelled && $previousStatus !== InterviewStatus::Cancelled) {
            $this->meetings->cancelForInterview($fresh);
            $this->activities->record($fresh, 'cancelled');
            event(new InterviewCancelled($fresh));
        } elseif (
            $fresh->status !== InterviewStatus::Cancelled
            && (
                $fresh->scheduled_at?->toDateTimeString() !== $previousScheduledAt
                || $fresh->duration_minutes !== $previousDuration
            )
        ) {
            $this->meetings->syncSchedule($fresh);
            $this->activities->record($fresh, 'rescheduled');
            event(new InterviewRescheduled($fresh));
        }

        if ($fresh->status === InterviewStatus::Completed && $previousStatus !== InterviewStatus::Completed) {
            $this->activities->record($fresh, 'completed');
            event(new InterviewCompleted($fresh));
        }

        if ($meetingInput !== [] && $fresh->currentMeeting === null) {
            $this->meetings->maybeCreateForInterview($fresh, $meetingInput);
            $fresh = $fresh->fresh(['application.candidate', 'application.jobOpening', 'interviewers', 'feedback', 'currentMeeting']) ?? $fresh;
        }

        return $fresh;
    }

    public function complete(Interview $interview): Interview
    {
        return $this->update($interview, ['status' => InterviewStatus::Completed]);
    }

    public function cancel(Interview $interview): Interview
    {
        return $this->update($interview, ['status' => InterviewStatus::Cancelled]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function submitFeedback(Interview $interview, User $interviewer, array $data): InterviewFeedback
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $assigned = $interview->interviewers()->whereKey($interviewer->id)->exists();
        $canManage = $interviewer->can('hr.recruitment.manage');

        if (! $assigned && ! $canManage) {
            throw ValidationException::withMessages([
                'interview_id' => ['Only assigned interviewers can submit feedback.'],
            ]);
        }

        $feedback = InterviewFeedback::query()->updateOrCreate(
            [
                'interview_id' => $interview->id,
                'user_id' => $interviewer->id,
            ],
            [
                'rating' => $data['rating'] ?? null,
                'strengths' => $data['strengths'] ?? null,
                'weaknesses' => $data['weaknesses'] ?? null,
                'recommendation' => $data['recommendation'] ?? InterviewRecommendation::Neutral,
                'comments' => $data['comments'] ?? null,
            ],
        );

        $this->activities->record($interview, 'feedback_submitted', $interviewer, [
            'rating' => $feedback->rating,
            'recommendation' => $feedback->recommendation?->value,
        ]);

        return $feedback->load('interviewer');
    }

    public function destroy(Interview $interview): void
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $this->meetings->cancelForInterview($interview);
        $interview->delete();
    }

    /**
     * @param  list<int>  $interviewerIds
     *
     * @throws ValidationException
     */
    protected function syncInterviewers(Interview $interview, array $interviewerIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $interviewerIds)));

        if ($ids === []) {
            $interview->interviewers()->sync([]);

            return;
        }

        $users = User::query()->whereKey($ids)->get();

        if ($users->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'interviewer_ids' => ['One or more interviewers were not found.'],
            ]);
        }

        foreach ($users as $user) {
            if (
                ! $user->can('hr.recruitment.view')
                && ! $user->can('hr.recruitment.manage')
                && ! $user->can('hr.recruitment.feedback')
                && ! $user->can('hr.view')
            ) {
                throw ValidationException::withMessages([
                    'interviewer_ids' => ['Interviewers must have recruitment access.'],
                ]);
            }
        }

        $interview->interviewers()->sync($ids);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractMeetingInput(array $data): array
    {
        $input = [];

        foreach (['meeting_provider', 'provider', 'meeting_url', 'join_url', 'meeting_password'] as $key) {
            if (array_key_exists($key, $data)) {
                $input[$key] = $data[$key];
            }
        }

        return $input;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }

    /**
     * @throws ValidationException
     */
    protected function assertApplicationOpen(JobApplication $application): void
    {
        if ($application->status->isTerminal()) {
            throw ValidationException::withMessages([
                'job_application_id' => ['Interviews cannot be scheduled for a closed application.'],
            ]);
        }
    }
}
