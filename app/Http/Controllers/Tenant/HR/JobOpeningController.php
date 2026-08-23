<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexJobOpeningRequest;
use App\Http\Requests\Tenant\HR\StoreJobOpeningImageRequest;
use App\Http\Requests\Tenant\HR\StoreJobOpeningRequest;
use App\Http\Requests\Tenant\HR\UpdateJobOpeningRequest;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\HR\JobOpeningResource;
use App\Models\Tenant\HR\JobOpening;
use App\Services\Tenant\HR\JobOpeningService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Recruitment job openings / listings.
 */
#[Group('HR / Job Listings')]
class JobOpeningController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  JobOpeningService  $openings
     */
    public function __construct(private readonly JobOpeningService $openings) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexJobOpeningRequest  $request
     * @return JsonResponse
     */
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

    /**
     * Create a resource.
     *
     * @param  StoreJobOpeningRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function store(StoreJobOpeningRequest $request): JsonResponse
    {
        $this->authorize('create', JobOpening::class);

        return $this->created(
            new JobOpeningResource($this->openings->store($request->validated())),
            'Job opening created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  JobOpening  $job_opening
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function show(JobOpening $job_opening): JsonResponse
    {
        $this->authorize('view', $job_opening);

        return $this->success(
            new JobOpeningResource($this->openings->show($job_opening)),
            'Job opening retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateJobOpeningRequest  $request
     * @param  JobOpening  $job_opening
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function update(UpdateJobOpeningRequest $request, JobOpening $job_opening): JsonResponse
    {
        $this->authorize('update', $job_opening);

        return $this->updated(
            new JobOpeningResource($this->openings->update($job_opening, $request->validated())),
            'Job opening updated successfully.',
        );
    }

    /**
     * Publish.
     *
     * @param  JobOpening  $job_opening
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Published job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function publish(JobOpening $job_opening): JsonResponse
    {
        $this->authorize('publish', $job_opening);

        return $this->updated(
            new JobOpeningResource($this->openings->publish($job_opening)),
            'Job opening published successfully.',
        );
    }

    /**
     * Pause.
     *
     * @param  JobOpening  $job_opening
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paused job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function pause(JobOpening $job_opening): JsonResponse
    {
        $this->authorize('publish', $job_opening);

        return $this->updated(
            new JobOpeningResource($this->openings->pause($job_opening)),
            'Job opening paused successfully.',
        );
    }

    /**
     * Close.
     *
     * @param  JobOpening  $job_opening
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Closed job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function close(JobOpening $job_opening): JsonResponse
    {
        $this->authorize('publish', $job_opening);

        return $this->updated(
            new JobOpeningResource($this->openings->close($job_opening)),
            'Job opening closed successfully.',
        );
    }

    /**
     * Cancel.
     *
     * @param  JobOpening  $job_opening
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Cancelled job opening.', type: 'array{success: true, message: string, data: JobOpeningResource, meta: null, errors: null}')]
    public function cancel(JobOpening $job_opening): JsonResponse
    {
        $this->authorize('publish', $job_opening);

        return $this->updated(
            new JobOpeningResource($this->openings->cancel($job_opening)),
            'Job opening cancelled successfully.',
        );
    }

    /**
     * Image.
     *
     * @param  StoreJobOpeningImageRequest  $request
     * @param  JobOpening  $job_opening
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Job listing image.', type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}')]
    public function image(StoreJobOpeningImageRequest $request, JobOpening $job_opening): JsonResponse
    {
        $this->authorize('update', $job_opening);

        return $this->created(
            new MediaResource($this->openings->addImage($job_opening, $request->file('file'))),
            'Job opening image uploaded successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  JobOpening  $job_opening
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted job opening.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(JobOpening $job_opening): JsonResponse
    {
        $this->authorize('delete', $job_opening);
        $this->openings->destroy($job_opening);

        return $this->deleted('Job opening deleted successfully.');
    }
}
