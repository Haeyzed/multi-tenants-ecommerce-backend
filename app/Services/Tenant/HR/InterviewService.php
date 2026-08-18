<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\InterviewRecommendation;
use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\InterviewType;
use App\Events\InterviewCompleted;
use App\Events\InterviewScheduled;
use App\Models\Tenant\Interview;
use App\Models\Tenant\InterviewFeedback;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\User;
use Illuminate\Validation\ValidationException;

/**
 * Interviews belong to applications, not to a separate candidate calendar.
 */
class InterviewService
{
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly RecruitmentActivityService $activities,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Interview
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $application = JobApplication::query()->findOrFail($data['job_application_id']);
        $this->assertApplicationOpen($application);

        $interview = Interview::query()->create([
            'job_application_id' => $application->id,
            'interview_type' => $data['interview_type'] ?? InterviewType::Other,
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'location' => $data['location'] ?? null,
            'meeting_url' => $data['meeting_url'] ?? null,
            'status' => $data['status'] ?? InterviewStatus::Scheduled,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncInterviewers($interview, $data['interviewer_ids'] ?? []);

        $this->activities->record($interview, 'scheduled', null, [
            'job_application_id' => $application->id,
            'status' => $interview->status->value,
        ]);

        event(new InterviewScheduled($interview));

        return $interview->load(['application.candidate', 'interviewers', 'feedback']);
    }

    public function show(Interview $interview): Interview
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $interview->load(['application.candidate', 'application.jobOpening', 'interviewers', 'feedback.interviewer']);
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

        $previousStatus = $interview->status;

        if (isset($data['scheduled_at']) && $interview->status === InterviewStatus::Scheduled) {
            $data['status'] = $data['status'] ?? InterviewStatus::Rescheduled;
        }

        $interview->fill($data);
        $interview->save();

        if (is_array($interviewerIds)) {
            $this->syncInterviewers($interview, $interviewerIds);
        }

        $fresh = $interview->fresh(['application.candidate', 'interviewers', 'feedback']) ?? $interview;

        if ($fresh->status === InterviewStatus::Completed && $previousStatus !== InterviewStatus::Completed) {
            $this->activities->record($fresh, 'completed');
            event(new InterviewCompleted($fresh));
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
