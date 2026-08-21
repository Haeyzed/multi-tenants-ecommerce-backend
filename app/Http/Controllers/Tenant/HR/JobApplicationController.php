<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\HireCandidateRequest;
use App\Http\Requests\Tenant\HR\IndexJobApplicationRequest;
use App\Http\Requests\Tenant\HR\IndexRecruitmentActivityRequest;
use App\Http\Requests\Tenant\HR\MoveApplicationStageRequest;
use App\Http\Requests\Tenant\HR\StoreJobApplicationRequest;
use App\Http\Requests\Tenant\HR\UpdateJobApplicationRequest;
use App\Http\Resources\Tenant\HR\EmployeeResource;
use App\Http\Resources\Tenant\HR\JobApplicationResource;
use App\Http\Resources\Tenant\HR\RecruitmentActivityResource;
use App\Models\HR\JobApplication;
use App\Models\HR\RecruitmentStage;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\HiringService;
use App\Services\Tenant\HR\JobApplicationService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Recruitment applications.
 */
#[Group('HR / Applications')]
class JobApplicationController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  JobApplicationService  $applications
     * @param  HiringService  $hiring
     */
    public function __construct(
        private readonly JobApplicationService $applications,
        private readonly HiringService $hiring,
    ) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexJobApplicationRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated job applications.', type: 'array{success: true, message: string, data: JobApplicationResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexJobApplicationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', JobApplication::class);

        $applications = $this->applications->list($request->validated());

        return $this->success(
            JobApplicationResource::collection($applications->items()),
            'Job applications retrieved successfully.',
            $this->paginationMeta($applications),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreJobApplicationRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created job application.', type: 'array{success: true, message: string, data: JobApplicationResource, meta: null, errors: null}')]
    public function store(StoreJobApplicationRequest $request): JsonResponse
    {
        $this->authorize('create', JobApplication::class);

        /** @var User $actor */
        $actor = $request->user();

        return $this->created(
            new JobApplicationResource($this->applications->store($request->validated(), $actor)),
            'Job application created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  JobApplication  $job_application
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A job application.', type: 'array{success: true, message: string, data: JobApplicationResource, meta: null, errors: null}')]
    public function show(JobApplication $job_application): JsonResponse
    {
        $this->authorize('view', $job_application);

        return $this->success(
            new JobApplicationResource($this->applications->show($job_application)),
            'Job application retrieved successfully.',
        );
    }

    /**
     * Activities.
     *
     * @param  IndexRecruitmentActivityRequest  $request
     * @param  JobApplication  $job_application
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Application activity feed.', type: 'array{success: true, message: string, data: RecruitmentActivityResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function activities(IndexRecruitmentActivityRequest $request, JobApplication $job_application): JsonResponse
    {
        $this->authorize('view', $job_application);

        $activities = $this->applications->listActivities($job_application, $request->validated());

        return $this->success(
            RecruitmentActivityResource::collection($activities->items()),
            'Application activity retrieved successfully.',
            $this->paginationMeta($activities),
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateJobApplicationRequest  $request
     * @param  JobApplication  $job_application
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated job application.', type: 'array{success: true, message: string, data: JobApplicationResource, meta: null, errors: null}')]
    public function update(UpdateJobApplicationRequest $request, JobApplication $job_application): JsonResponse
    {
        $this->authorize('update', $job_application);

        /** @var User $actor */
        $actor = $request->user();

        return $this->updated(
            new JobApplicationResource($this->applications->update($job_application, $request->validated(), $actor)),
            'Job application updated successfully.',
        );
    }

    /**
     * Move stage.
     *
     * @param  MoveApplicationStageRequest  $request
     * @param  JobApplication  $job_application
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Moved application stage.', type: 'array{success: true, message: string, data: JobApplicationResource, meta: null, errors: null}')]
    public function moveStage(MoveApplicationStageRequest $request, JobApplication $job_application): JsonResponse
    {
        $this->authorize('moveStage', $job_application);

        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();
        $stage = RecruitmentStage::query()->findOrFail($data['recruitment_stage_id']);

        return $this->updated(
            new JobApplicationResource($this->applications->moveStage(
                $job_application,
                $stage,
                null,
                $actor,
                $data['notes'] ?? null,
            )),
            'Application stage updated successfully.',
        );
    }

    /**
     * Hire.
     *
     * @param  HireCandidateRequest  $request
     * @param  JobApplication  $job_application
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Hired employee.', type: 'array{success: true, message: string, data: EmployeeResource, meta: null, errors: null}')]
    public function hire(HireCandidateRequest $request, JobApplication $job_application): JsonResponse
    {
        $this->authorize('hire', $job_application);

        /** @var User $actor */
        $actor = $request->user();

        return $this->created(
            new EmployeeResource($this->hiring->convert($job_application, $actor, $request->validated())),
            'Candidate converted to employee successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  JobApplication  $job_application
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted job application.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(JobApplication $job_application): JsonResponse
    {
        $this->authorize('delete', $job_application);
        $this->applications->destroy($job_application);

        return $this->deleted('Job application deleted successfully.');
    }
}
