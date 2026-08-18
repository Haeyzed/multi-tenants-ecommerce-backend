<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\ApplicationSource;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Events\JobApplicationReceived;
use App\Events\JobApplicationStageChanged;
use App\Models\Tenant\ApplicationStageHistory;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\JobOpening;
use App\Models\Tenant\RecruitmentActivity;
use App\Models\Tenant\RecruitmentStage;
use App\Models\Tenant\User;
use App\Services\Landlord\Feature\UsageLimiter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recruitment applications and stage movement.
 */
class JobApplicationService
{
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly CandidateService $candidates,
        private readonly RecruitmentStageService $stages,
        private readonly UsageLimiter $usageLimiter,
        private readonly RecruitmentActivityService $activities,
    ) {}

    /**
     * @param  array{search?: string|null, status?: string|null, job_opening_id?: int|null, candidate_id?: int|null, recruitment_stage_id?: int|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JobApplication>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return JobApplication::query()
            ->with(['jobOpening', 'candidate', 'stage', 'hiredEmployee.user'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function store(array $data, ?User $actor = null, bool $public = false): JobApplication
    {
        if ($public) {
            $this->hrSettings->assertPublicJobApplicationsEnabled();
        } else {
            $this->hrSettings->assertRecruitmentEnabled();
        }

        $opening = JobOpening::query()->findOrFail($data['job_opening_id']);
        $this->assertAcceptsApplications($opening);
        $this->usageLimiter->assertLimitIfPresent('applications_per_month');

        $candidate = $this->candidates->findOrCreate($data);

        if (JobApplication::query()
            ->where('job_opening_id', $opening->id)
            ->where('candidate_id', $candidate->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This candidate has already applied to this job listing.'],
            ]);
        }

        $stage = $this->stages->defaultStage();
        $status = $public
            ? JobApplicationStatus::Received
            : ($data['status'] ?? JobApplicationStatus::Received);

        if ($status instanceof JobApplicationStatus && $status !== JobApplicationStatus::Received) {
            $stage = $this->stages->stageForKind($status);
        } else {
            $status = $stage->kind;
        }

        $application = DB::transaction(function () use ($opening, $candidate, $stage, $status, $data, $actor, $public): JobApplication {
            $application = JobApplication::query()->create([
                'job_opening_id' => $opening->id,
                'candidate_id' => $candidate->id,
                'recruitment_stage_id' => $stage->id,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'source' => $data['source'] ?? ($public ? ApplicationSource::Website : ApplicationSource::Internal),
                'applied_at' => now(),
                'status' => $status,
                'cover_letter' => $data['cover_letter'] ?? null,
                'notes' => $public ? null : ($data['notes'] ?? null),
            ]);

            $this->recordHistory($application, null, $stage, null, $status, $actor, 'Application received.');

            return $application;
        });

        event(new JobApplicationReceived($application));
        $this->activities->record($application, 'received', $actor, [
            'job_opening_id' => $opening->id,
            'candidate_id' => $candidate->id,
        ]);

        return $application->load(['jobOpening', 'candidate', 'stage', 'hiredEmployee.user']);
    }

    public function applyPublic(array $data, ?UploadedFile $resume = null): JobApplication
    {
        $application = $this->store($data, null, true);

        if ($resume !== null && $application->candidate !== null) {
            $this->candidates->addResume($application->candidate, $resume);
        }

        return $application->load(['jobOpening', 'candidate', 'stage']);
    }

    public function show(JobApplication $application): JobApplication
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $application->load([
            'jobOpening',
            'candidate',
            'stage',
            'hiredEmployee.user',
            'stageHistory.toStage',
            'stageHistory.fromStage',
            'stageHistory.changedByUser',
            'interviews.interviewers',
            'offers',
        ]);
    }

    /**
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, RecruitmentActivity>
     */
    public function listActivities(JobApplication $application, array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $this->activities->listForApplication($application, $params);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JobApplication $application, array $data, ?User $actor = null): JobApplication
    {
        $this->hrSettings->assertRecruitmentEnabled();

        unset($data['job_opening_id'], $data['candidate_id'], $data['hired_employee_id']);

        if (isset($data['email'])) {
            $data['email'] = strtolower((string) $data['email']);
        }

        $nextStage = isset($data['recruitment_stage_id'])
            ? RecruitmentStage::query()->findOrFail($data['recruitment_stage_id'])
            : null;
        $nextStatus = $data['status'] ?? null;

        if ($nextStage !== null || $nextStatus instanceof JobApplicationStatus) {
            return $this->moveStage(
                $application,
                $nextStage,
                $nextStatus instanceof JobApplicationStatus ? $nextStatus : null,
                $actor,
                isset($data['notes']) ? (string) $data['notes'] : null,
            );
        }

        $application->fill($data);
        $application->save();

        return $application->fresh(['jobOpening', 'candidate', 'stage', 'hiredEmployee.user']) ?? $application;
    }

    /**
     * @throws ValidationException
     */
    public function moveStage(
        JobApplication $application,
        ?RecruitmentStage $stage,
        ?JobApplicationStatus $status,
        ?User $actor = null,
        ?string $notes = null,
    ): JobApplication {
        $this->hrSettings->assertRecruitmentEnabled();

        if ($application->status === JobApplicationStatus::Hired) {
            throw ValidationException::withMessages([
                'status' => ['A hired application cannot change stage. Use hiring conversion instead.'],
            ]);
        }

        if ($stage === null && $status !== null) {
            $stage = $this->stages->stageForKind($status);
        }

        if ($stage === null) {
            throw ValidationException::withMessages([
                'recruitment_stage_id' => ['A recruitment stage is required.'],
            ]);
        }

        $status ??= $stage->kind;

        if ($status === JobApplicationStatus::Hired) {
            throw ValidationException::withMessages([
                'status' => ['Hiring must go through the candidate conversion flow.'],
            ]);
        }

        $fromStage = $application->stage;
        $fromStatus = $application->status;

        $application->recruitment_stage_id = $stage->id;
        $application->status = $status;
        $application->save();

        $this->recordHistory($application, $fromStage, $stage, $fromStatus, $status, $actor, $notes);

        event(new JobApplicationStageChanged($application, $fromStatus, $status));

        $this->activities->record($application, 'stage_changed', $actor, [
            'from_status' => $fromStatus instanceof JobApplicationStatus ? $fromStatus->value : null,
            'to_status' => $status->value,
            'to_stage_id' => $stage->id,
        ]);

        return $application->fresh(['jobOpening', 'candidate', 'stage', 'hiredEmployee.user']) ?? $application;
    }

    public function destroy(JobApplication $application): void
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $application->delete();
    }

    public function recordHistory(
        JobApplication $application,
        ?RecruitmentStage $fromStage,
        ?RecruitmentStage $toStage,
        ?JobApplicationStatus $fromStatus,
        JobApplicationStatus $toStatus,
        ?User $actor,
        ?string $notes,
    ): ApplicationStageHistory {
        return ApplicationStageHistory::query()->create([
            'job_application_id' => $application->id,
            'from_stage_id' => $fromStage?->id,
            'to_stage_id' => $toStage?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $actor?->id,
            'notes' => $notes,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function assertAcceptsApplications(JobOpening $opening): void
    {
        if (! $opening->isAcceptingApplications()) {
            throw ValidationException::withMessages([
                'job_opening_id' => ['Applications can only be submitted to published job listings that are still open.'],
            ]);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
