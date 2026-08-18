<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOfferStatus;
use App\Events\JobOfferAccepted;
use App\Events\JobOfferSent;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\JobOffer;
use App\Models\Tenant\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Offers belong to applications. Accepted offers do not create employees.
 */
class JobOfferService
{
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly JobApplicationService $applications,
        private readonly RecruitmentStageService $stages,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $application = JobApplication::query()->with('jobOpening')->findOrFail($data['job_application_id']);
        $this->assertCanOffer($application);

        $offer = JobOffer::query()->create([
            'job_application_id' => $application->id,
            'position' => $data['position'] ?? $application->jobOpening?->title,
            'salary' => Money::add((string) $data['salary'], '0'),
            'currency' => strtoupper((string) ($data['currency'] ?? $this->hrSettings->payrollCurrency())),
            'start_date' => $data['start_date'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'status' => JobOfferStatus::Draft,
            'notes' => $data['notes'] ?? null,
        ]);

        return $offer->load(['application.candidate', 'application.jobOpening']);
    }

    public function show(JobOffer $offer): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();
        $this->expireIfNeeded($offer);

        return $offer->load(['application.candidate', 'application.jobOpening', 'approvedByUser']);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(JobOffer $offer, array $data): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();
        $this->expireIfNeeded($offer);

        if (! in_array($offer->status, [JobOfferStatus::Draft, JobOfferStatus::PendingApproval], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or pending offers can be updated.'],
            ]);
        }

        unset($data['job_application_id'], $data['status'], $data['approved_by']);

        if (isset($data['salary'])) {
            $data['salary'] = Money::add((string) $data['salary'], '0');
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        $offer->fill($data);
        $offer->save();

        return $offer->fresh(['application.candidate', 'application.jobOpening']) ?? $offer;
    }

    /**
     * @throws ValidationException
     */
    public function submitForApproval(JobOffer $offer): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();

        if ($offer->status !== JobOfferStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Only draft offers can be submitted for approval.'],
            ]);
        }

        $offer->status = JobOfferStatus::PendingApproval;
        $offer->save();

        return $offer->fresh(['application.candidate']) ?? $offer;
    }

    /**
     * @throws ValidationException
     */
    public function approve(JobOffer $offer, User $actor): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();

        if ($offer->status !== JobOfferStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'status' => ['Only pending offers can be approved.'],
            ]);
        }

        $offer->status = JobOfferStatus::Draft;
        $offer->approved_by = $actor->id;
        $offer->save();

        return $offer->fresh(['application.candidate', 'approvedByUser']) ?? $offer;
    }

    /**
     * @throws ValidationException
     */
    public function send(JobOffer $offer, ?User $actor = null): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();
        $this->expireIfNeeded($offer);

        if ($this->hrSettings->offerApprovalRequired() && $offer->approved_by === null) {
            if ($offer->status === JobOfferStatus::Draft) {
                return $this->submitForApproval($offer);
            }

            throw ValidationException::withMessages([
                'status' => ['This offer must be approved before it can be sent.'],
            ]);
        }

        if (! in_array($offer->status, [JobOfferStatus::Draft, JobOfferStatus::PendingApproval], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft offers can be sent.'],
            ]);
        }

        if ($this->hrSettings->offerApprovalRequired() && $offer->status === JobOfferStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'status' => ['This offer must be approved before it can be sent.'],
            ]);
        }

        $offer->status = JobOfferStatus::Sent;
        $offer->sent_at = now();
        $offer->save();

        $application = $offer->application;
        $stage = $this->stages->stageForKind(JobApplicationStatus::Offered);
        $this->applications->moveStage($application, $stage, JobApplicationStatus::Offered, $actor, 'Offer sent.');

        event(new JobOfferSent($offer));

        return $offer->fresh(['application.candidate', 'application.jobOpening']) ?? $offer;
    }

    /**
     * @throws ValidationException
     */
    public function accept(JobOffer $offer, ?User $actor = null): JobOffer
    {
        return $this->decide($offer, JobOfferStatus::Accepted, $actor);
    }

    /**
     * @throws ValidationException
     */
    public function reject(JobOffer $offer, ?User $actor = null): JobOffer
    {
        return $this->decide($offer, JobOfferStatus::Rejected, $actor);
    }

    /**
     * @throws ValidationException
     */
    public function withdraw(JobOffer $offer): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();
        $this->expireIfNeeded($offer);

        if ($offer->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => ['This offer can no longer be withdrawn.'],
            ]);
        }

        $offer->status = JobOfferStatus::Withdrawn;
        $offer->decided_at = now();
        $offer->save();

        return $offer->fresh(['application.candidate']) ?? $offer;
    }

    public function expireIfNeeded(JobOffer $offer): JobOffer
    {
        if ($offer->isExpired()) {
            $offer->status = JobOfferStatus::Expired;
            $offer->decided_at = now();
            $offer->save();
        }

        return $offer;
    }

    /**
     * @throws ValidationException
     */
    protected function decide(JobOffer $offer, JobOfferStatus $decision, ?User $actor): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();
        $this->expireIfNeeded($offer);

        if ($offer->status !== JobOfferStatus::Sent) {
            throw ValidationException::withMessages([
                'status' => ['Only sent offers can be accepted or rejected.'],
            ]);
        }

        $offer->status = $decision;
        $offer->decided_at = now();
        $offer->save();

        if ($decision === JobOfferStatus::Rejected) {
            $application = $offer->application;
            $stage = $this->stages->stageForKind(JobApplicationStatus::Rejected);
            $this->applications->moveStage($application, $stage, JobApplicationStatus::Rejected, $actor, 'Offer rejected.');
        }

        if ($decision === JobOfferStatus::Accepted) {
            event(new JobOfferAccepted($offer));
        }

        return $offer->fresh(['application.candidate', 'application.jobOpening']) ?? $offer;
    }

    /**
     * @throws ValidationException
     */
    protected function assertCanOffer(JobApplication $application): void
    {
        if ($application->status->isTerminal()) {
            throw ValidationException::withMessages([
                'job_application_id' => ['Offers cannot be created for a closed application.'],
            ]);
        }

        if ($this->hrSettings->interviewRequiredBeforeOffer()) {
            $completed = $application->interviews()->where('status', InterviewStatus::Completed)->exists();

            if (! $completed) {
                throw ValidationException::withMessages([
                    'job_application_id' => ['A completed interview is required before creating an offer.'],
                ]);
            }
        }
    }
}
