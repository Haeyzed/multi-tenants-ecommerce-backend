<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexJobApplicationRequest;
use App\Http\Requests\Tenant\HR\StoreJobApplicationRequest;
use App\Http\Requests\Tenant\HR\UpdateJobApplicationRequest;
use App\Http\Resources\Tenant\HR\JobApplicationResource;
use App\Models\Tenant\JobApplication;
use App\Services\Tenant\HR\JobApplicationService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Recruitment applications.
 */
#[Group('HR')]
class JobApplicationController extends Controller
{
    public function __construct(private readonly JobApplicationService $applications) {}

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

    #[Response(status: 201, description: 'Created job application.', type: 'array{success: true, message: string, data: JobApplicationResource, meta: null, errors: null}')]
    public function store(StoreJobApplicationRequest $request): JsonResponse
    {
        $this->authorize('create', JobApplication::class);

        return $this->created(
            new JobApplicationResource($this->applications->store($request->validated())),
            'Job application created successfully.',
        );
    }

    #[Response(status: 200, description: 'A job application.', type: 'array{success: true, message: string, data: JobApplicationResource, meta: null, errors: null}')]
    public function show(JobApplication $job_application): JsonResponse
    {
        $this->authorize('view', $job_application);

        return $this->success(
            new JobApplicationResource($this->applications->show($job_application)),
            'Job application retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated job application.', type: 'array{success: true, message: string, data: JobApplicationResource, meta: null, errors: null}')]
    public function update(UpdateJobApplicationRequest $request, JobApplication $job_application): JsonResponse
    {
        $this->authorize('update', $job_application);

        return $this->updated(
            new JobApplicationResource($this->applications->update($job_application, $request->validated())),
            'Job application updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted job application.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(JobApplication $job_application): JsonResponse
    {
        $this->authorize('delete', $job_application);
        $this->applications->destroy($job_application);

        return $this->deleted('Job application deleted successfully.');
    }
}
