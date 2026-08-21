<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOfferStatus;
use App\Events\JobOfferAccepted;
use App\Events\JobOfferRejected;
use App\Events\JobOfferSent;
use App\Models\HR\JobApplication;
use App\Models\HR\JobOffer;
use App\Models\Tenant\User;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Offers belong to applications. Accepted offers do not create employees.
 */
class JobOfferService
{
    /**
     * Create a new class instance.
     *
     * @param  HrSettingsService  $hrSettings
     * @param  JobApplicationService  $applications
     * @param  RecruitmentStageService  $stages
     * @param  RecruitmentActivityService  $activities
     */
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly JobApplicationService $applications,
        private readonly RecruitmentStageService $stages,
        private readonly RecruitmentActivityService $activities,
    ) {}

    /**
     * Create a resource.
     *
     * @param  array<string, mixed>  $data
     * @return JobOffer
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

        $this->activities->record($offer, 'created', null, [
            'job_application_id' => $application->id,
            'status' => $offer->status->value,
        ]);

        return $offer->load(['application.candidate', 'application.jobOpening']);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  JobOffer  $offer
     * @return JobOffer
     */
    public function show(JobOffer $offer): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();
        $this->expireIfNeeded($offer);

        return $offer->load(['application.candidate', 'application.jobOpening', 'approvedByUser']);
    }

    /**
     * Show public by token.
     *
     * @param  string  $token
     * @return JobOffer
     *
     * @throws ValidationException
     */
    public function showPublicByToken(string $token): JobOffer
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $offer = $this->offerFromToken($token);
        $this->expireIfNeeded($offer);

        if ($offer->status !== JobOfferStatus::Sent) {
            throw ValidationException::withMessages([
                'token' => ['This offer is no longer available.'],
            ]);
        }

        return $offer->load(['application.jobOpening']);
    }

    /**
     * Update a resource.
     *
     * @param  JobOffer  $offer
     * @param  array<string, mixed>  $data
     * @return JobOffer
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

        unset($data['job_application_id'], $data['status'], $data['approved_by'], $data['response_token_hash']);

        if (isset($data['salary'])) {
            $data['salary'] = Money::add((string) $data['salary'], '0');
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        $offer->fill($data);
        $offer->save();

        $this->activities->record($offer, 'updated', null, ['status' => $offer->status->value]);

        return $offer->fresh(['application.candidate', 'application.jobOpening']) ?? $offer;
    }

    /**
     * Submit for approval.
     *
     * @param  JobOffer  $offer
     * @return JobOffer
     *
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

        $this->activities->record($offer, 'submitted_for_approval', null, ['status' => $offer->status->value]);

        return $offer->fresh(['application.candidate']) ?? $offer;
    }

    /**
     * Approve.
     *
     * @param  JobOffer  $offer
     * @param  User  $actor
     * @return JobOffer
     *
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

        $this->activities->record($offer, 'approved', $actor, ['status' => $offer->status->value]);

        return $offer->fresh(['application.candidate', 'approvedByUser']) ?? $offer;
    }

    /**
     * Send.
     *
     * @param  JobOffer  $offer
     * @param  ?User  $actor
     * @return JobOffer
     *
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

        $token = Str::random(64);

        $offer->status = JobOfferStatus::Sent;
        $offer->sent_at = now();
        $offer->response_token_hash = hash('sha256', $token);
        $offer->save();

        $application = $offer->application;
        $stage = $this->stages->stageForKind(JobApplicationStatus::Offered);
        $this->applications->moveStage($application, $stage, JobApplicationStatus::Offered, $actor, 'Offer sent.');

        $this->activities->record($offer, 'sent', $actor, ['status' => $offer->status->value]);

        event(new JobOfferSent($offer, $token));

        return $offer->fresh(['application.candidate', 'application.jobOpening']) ?? $offer;
    }

    /**
     * Public response url.
     *
     * @param  string  $token
     * @return string
     */
    public function publicResponseUrl(string $token): string
    {
        return route('tenant.public.offers.show', ['token' => $token], absolute: true);
    }

    /**
     * Accept.
     *
     * @param  JobOffer  $offer
     * @param  ?User  $actor
     * @return JobOffer
     *
     * @throws ValidationException
     */
    public function accept(JobOffer $offer, ?User $actor = null): JobOffer
    {
        return $this->decide($offer, JobOfferStatus::Accepted, $actor);
    }

    /**
     * Reject.
     *
     * @param  JobOffer  $offer
     * @param  ?User  $actor
     * @return JobOffer
     *
     * @throws ValidationException
     */
    public function reject(JobOffer $offer, ?User $actor = null): JobOffer
    {
        return $this->decide($offer, JobOfferStatus::Rejected, $actor);
    }

    /**
     * Accept public by token.
     *
     * @param  string  $token
     * @return JobOffer
     *
     * @throws ValidationException
     */
    public function acceptPublicByToken(string $token): JobOffer
    {
        return $this->accept($this->showPublicByToken($token));
    }

    /**
     * Reject public by token.
     *
     * @param  string  $token
     * @return JobOffer
     *
     * @throws ValidationException
     */
    public function rejectPublicByToken(string $token): JobOffer
    {
        return $this->reject($this->showPublicByToken($token));
    }

    /**
     * Withdraw.
     *
     * @param  JobOffer  $offer
     * @return JobOffer
     *
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
        $offer->response_token_hash = null;
        $offer->save();

        $this->activities->record($offer, 'withdrawn', null, ['status' => $offer->status->value]);

        return $offer->fresh(['application.candidate']) ?? $offer;
    }

    /**
     * Expire if needed.
     *
     * @param  JobOffer  $offer
     * @return JobOffer
     */
    public function expireIfNeeded(JobOffer $offer): JobOffer
    {
        if ($offer->isExpired()) {
            $offer->status = JobOfferStatus::Expired;
            $offer->decided_at = now();
            $offer->response_token_hash = null;
            $offer->save();
            $this->activities->record($offer, 'expired', null, ['status' => $offer->status->value]);
        }

        return $offer;
    }

    /**
     * Decide.
     *
     * @param  JobOffer  $offer
     * @param  JobOfferStatus  $decision
     * @param  ?User  $actor
     * @return JobOffer
     *
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
        $offer->response_token_hash = null;
        $offer->save();

        $this->activities->record($offer, $decision->value, $actor, ['status' => $decision->value]);

        if ($decision === JobOfferStatus::Rejected) {
            $application = $offer->application;
            $stage = $this->stages->stageForKind(JobApplicationStatus::Rejected);
            $this->applications->moveStage($application, $stage, JobApplicationStatus::Rejected, $actor, 'Offer rejected.');
            event(new JobOfferRejected($offer));
        }

        if ($decision === JobOfferStatus::Accepted) {
            event(new JobOfferAccepted($offer));
        }

        return $offer->fresh(['application.candidate', 'application.jobOpening']) ?? $offer;
    }

    /**
     * Offer from token.
     *
     * @param  string  $token
     * @return JobOffer
     *
     * @throws ValidationException
     */
    protected function offerFromToken(string $token): JobOffer
    {
        $token = trim($token);

        if ($token === '') {
            throw ValidationException::withMessages([
                'token' => ['The offer token is invalid.'],
            ]);
        }

        $offer = JobOffer::query()
            ->where('response_token_hash', hash('sha256', $token))
            ->first();

        if ($offer === null) {
            throw ValidationException::withMessages([
                'token' => ['The offer token is invalid.'],
            ]);
        }

        return $offer;
    }

    /**
     * Assert can offer.
     *
     * @param  JobApplication  $application
     * @return void
     *
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
