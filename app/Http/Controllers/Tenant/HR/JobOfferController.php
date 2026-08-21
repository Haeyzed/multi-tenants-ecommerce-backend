<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\StoreJobOfferRequest;
use App\Http\Requests\Tenant\HR\UpdateJobOfferRequest;
use App\Http\Resources\Tenant\HR\JobOfferResource;
use App\Models\HR\JobOffer;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\JobOfferService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('HR / Offers')]
class JobOfferController extends Controller
{
    public function __construct(private readonly JobOfferService $offers) {}

    #[Response(status: 201, description: 'Created offer.', type: 'array{success: true, message: string, data: JobOfferResource, meta: null, errors: null}')]
    public function store(StoreJobOfferRequest $request): JsonResponse
    {
        $this->authorize('create', JobOffer::class);

        return $this->created(
            new JobOfferResource($this->offers->store($request->validated())),
            'Offer created successfully.',
        );
    }

    #[Response(status: 200, description: 'An offer.', type: 'array{success: true, message: string, data: JobOfferResource, meta: null, errors: null}')]
    public function show(JobOffer $job_offer): JsonResponse
    {
        $this->authorize('view', $job_offer);

        return $this->success(
            new JobOfferResource($this->offers->show($job_offer)),
            'Offer retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated offer.', type: 'array{success: true, message: string, data: JobOfferResource, meta: null, errors: null}')]
    public function update(UpdateJobOfferRequest $request, JobOffer $job_offer): JsonResponse
    {
        $this->authorize('update', $job_offer);

        return $this->updated(
            new JobOfferResource($this->offers->update($job_offer, $request->validated())),
            'Offer updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Approved offer.', type: 'array{success: true, message: string, data: JobOfferResource, meta: null, errors: null}')]
    public function approve(JobOffer $job_offer): JsonResponse
    {
        $this->authorize('approve', $job_offer);

        /** @var User $actor */
        $actor = request()->user();

        return $this->updated(
            new JobOfferResource($this->offers->approve($job_offer, $actor)),
            'Offer approved successfully.',
        );
    }

    #[Response(status: 200, description: 'Sent offer.', type: 'array{success: true, message: string, data: JobOfferResource, meta: null, errors: null}')]
    public function send(JobOffer $job_offer): JsonResponse
    {
        $this->authorize('send', $job_offer);

        /** @var User $actor */
        $actor = request()->user();

        return $this->updated(
            new JobOfferResource($this->offers->send($job_offer, $actor)),
            'Offer sent successfully.',
        );
    }

    #[Response(status: 200, description: 'Accepted offer.', type: 'array{success: true, message: string, data: JobOfferResource, meta: null, errors: null}')]
    public function accept(JobOffer $job_offer): JsonResponse
    {
        $this->authorize('send', $job_offer);

        /** @var User $actor */
        $actor = request()->user();

        return $this->updated(
            new JobOfferResource($this->offers->accept($job_offer, $actor)),
            'Offer accepted successfully.',
        );
    }

    #[Response(status: 200, description: 'Rejected offer.', type: 'array{success: true, message: string, data: JobOfferResource, meta: null, errors: null}')]
    public function reject(JobOffer $job_offer): JsonResponse
    {
        $this->authorize('send', $job_offer);

        /** @var User $actor */
        $actor = request()->user();

        return $this->updated(
            new JobOfferResource($this->offers->reject($job_offer, $actor)),
            'Offer rejected successfully.',
        );
    }

    #[Response(status: 200, description: 'Withdrawn offer.', type: 'array{success: true, message: string, data: JobOfferResource, meta: null, errors: null}')]
    public function withdraw(JobOffer $job_offer): JsonResponse
    {
        $this->authorize('update', $job_offer);

        return $this->updated(
            new JobOfferResource($this->offers->withdraw($job_offer)),
            'Offer withdrawn successfully.',
        );
    }
}
