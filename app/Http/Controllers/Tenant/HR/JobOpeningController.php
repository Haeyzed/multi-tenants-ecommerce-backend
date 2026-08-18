<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexJobOpeningRequest;
use App\Http\Requests\Tenant\HR\StoreJobOpeningRequest;
use App\Http\Requests\Tenant\HR\UpdateJobOpeningRequest;
use App\Http\Resources\Tenant\HR\JobOpeningResource;
use App\Models\Tenant\JobOpening;
use App\Services\Tenant\HR\JobOpeningService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Recruitment job openings.
 */
#[Group('HR')]
class JobOpeningController extends Controller
{
    public function __construct(private readonly JobOpeningService $openings) {}

    #[Response(status: 200, description: 'Paginated job openings.', type: 'array{success: true, message: string, data: JobOpeningResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexJobOpeningRequest $request): JsonResponse
    {
        $this->authorize('viewAny', JobOpening::class);

        $openings = $this->openings->list($request->validated());

        return $this->success(
            JobOpeningResource::collection($openings->items()),
            'Job openings retrieved successfully.',
            $this->paginationMeta($openings),
        );
    }

    #[Response(status: 201, description: 'Created job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function store(StoreJobOpeningRequest $request): JsonResponse
    {
        $this->authorize('create', JobOpening::class);

        return $this->created(
            new JobOpeningResource($this->openings->store($request->validated())),
            'Job opening created successfully.',
        );
    }

    #[Response(status: 200, description: 'A job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function show(JobOpening $job_opening): JsonResponse
    {
        $this->authorize('view', $job_opening);

        return $this->success(
            new JobOpeningResource($this->openings->show($job_opening)),
            'Job opening retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function update(UpdateJobOpeningRequest $request, JobOpening $job_opening): JsonResponse
    {
        $this->authorize('update', $job_opening);

        return $this->updated(
            new JobOpeningResource($this->openings->update($job_opening, $request->validated())),
            'Job opening updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted job opening.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(JobOpening $job_opening): JsonResponse
    {
        $this->authorize('delete', $job_opening);
        $this->openings->destroy($job_opening);

        return $this->deleted('Job opening deleted successfully.');
    }
}
