<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexWorkLocationRequest;
use App\Http\Requests\Tenant\HR\StoreWorkLocationRequest;
use App\Http\Requests\Tenant\HR\UpdateWorkLocationRequest;
use App\Http\Resources\Tenant\HR\WorkLocationResource;
use App\Models\Tenant\HR\WorkLocation;
use App\Services\Tenant\HR\WorkLocationService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant HR work location endpoints.
 */
#[Group('HR')]
class WorkLocationController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  WorkLocationService  $locations
     */
    public function __construct(private readonly WorkLocationService $locations) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexWorkLocationRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated work locations.', type: 'array{success: true, message: string, data: WorkLocationResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexWorkLocationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', WorkLocation::class);

        $locations = $this->locations->list($request->validated());

        return $this->success(
            WorkLocationResource::collection($locations->items()),
            'Work locations retrieved successfully.',
            $this->paginationMeta($locations),
        );
    }

    /**
     * Return options for select inputs.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Work location options.', type: ApiResponseSchema::OPTIONS)]
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', WorkLocation::class);

        return $this->success(
            $this->locations->options(),
            'Work location options retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreWorkLocationRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created work location.', type: 'array{success: true, message: string, data: WorkLocationResource, meta: null, errors: null}')]
    public function store(StoreWorkLocationRequest $request): JsonResponse
    {
        $this->authorize('create', WorkLocation::class);

        return $this->created(
            new WorkLocationResource($this->locations->store($request->validated())),
            'Work location created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  WorkLocation  $work_location
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A work location.', type: 'array{success: true, message: string, data: WorkLocationResource, meta: null, errors: null}')]
    public function show(WorkLocation $work_location): JsonResponse
    {
        $this->authorize('view', $work_location);

        return $this->success(
            new WorkLocationResource($this->locations->show($work_location)),
            'Work location retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateWorkLocationRequest  $request
     * @param  WorkLocation  $work_location
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated work location.', type: 'array{success: true, message: string, data: WorkLocationResource, meta: null, errors: null}')]
    public function update(UpdateWorkLocationRequest $request, WorkLocation $work_location): JsonResponse
    {
        $this->authorize('update', $work_location);

        return $this->updated(
            new WorkLocationResource($this->locations->update($work_location, $request->validated())),
            'Work location updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  WorkLocation  $work_location
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted work location.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(WorkLocation $work_location): JsonResponse
    {
        $this->authorize('delete', $work_location);
        $this->locations->destroy($work_location);

        return $this->deleted('Work location deleted successfully.');
    }
}
