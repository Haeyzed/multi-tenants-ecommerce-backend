<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexDesignationRequest;
use App\Http\Requests\Tenant\HR\StoreDesignationRequest;
use App\Http\Requests\Tenant\HR\UpdateDesignationRequest;
use App\Http\Resources\Tenant\HR\DesignationResource;
use App\Models\HR\Designation;
use App\Services\Tenant\HR\DesignationService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant HR designation endpoints.
 */
#[Group('HR')]
class DesignationController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  DesignationService  $designationService
     */
    public function __construct(private readonly DesignationService $designationService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexDesignationRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated designations.', type: 'array{success: true, message: string, data: DesignationResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexDesignationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Designation::class);

        $designations = $this->designationService->list($request->validated());

        return $this->success(
            DesignationResource::collection($designations->items()),
            'Designations retrieved successfully.',
            $this->paginationMeta($designations),
        );
    }

    /**
     * Return options for select inputs.
     *
     * @param  IndexDesignationRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Designation options.', type: ApiResponseSchema::OPTIONS)]
    public function options(IndexDesignationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Designation::class);

        $departmentId = $request->validated('department_id');

        return $this->success(
            $this->designationService->options($departmentId !== null ? (int) $departmentId : null),
            'Designation options retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreDesignationRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created designation.', type: 'array{success: true, message: string, data: DesignationResource, meta: null, errors: null}')]
    public function store(StoreDesignationRequest $request): JsonResponse
    {
        $this->authorize('create', Designation::class);

        return $this->created(
            new DesignationResource($this->designationService->store($request->validated())),
            'Designation created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Designation  $designation
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A designation.', type: 'array{success: true, message: string, data: DesignationResource, meta: null, errors: null}')]
    public function show(Designation $designation): JsonResponse
    {
        $this->authorize('view', $designation);

        return $this->success(
            new DesignationResource($this->designationService->show($designation)),
            'Designation retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateDesignationRequest  $request
     * @param  Designation  $designation
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated designation.', type: 'array{success: true, message: string, data: DesignationResource, meta: null, errors: null}')]
    public function update(UpdateDesignationRequest $request, Designation $designation): JsonResponse
    {
        $this->authorize('update', $designation);

        return $this->updated(
            new DesignationResource($this->designationService->update($designation, $request->validated())),
            'Designation updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  Designation  $designation
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted designation.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Designation $designation): JsonResponse
    {
        $this->authorize('delete', $designation);
        $this->designationService->destroy($designation);

        return $this->deleted('Designation deleted successfully.');
    }
}
