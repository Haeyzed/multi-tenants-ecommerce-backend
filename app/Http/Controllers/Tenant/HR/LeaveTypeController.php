<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexLeaveTypeRequest;
use App\Http\Requests\Tenant\HR\StoreLeaveTypeRequest;
use App\Http\Requests\Tenant\HR\UpdateLeaveTypeRequest;
use App\Http\Resources\Tenant\HR\LeaveTypeResource;
use App\Models\HR\LeaveType;
use App\Services\Tenant\HR\LeaveTypeService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant-configurable leave types.
 */
#[Group('HR')]
class LeaveTypeController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  LeaveTypeService  $leaveTypeService
     */
    public function __construct(private readonly LeaveTypeService $leaveTypeService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexLeaveTypeRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated leave types.', type: 'array{success: true, message: string, data: LeaveTypeResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveType::class);

        $types = $this->leaveTypeService->list($request->validated());

        return $this->success(
            LeaveTypeResource::collection($types->items()),
            'Leave types retrieved successfully.',
            $this->paginationMeta($types),
        );
    }

    /**
     * Return options for select inputs.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Leave type options.', type: ApiResponseSchema::OPTIONS)]
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', LeaveType::class);

        return $this->success(
            $this->leaveTypeService->options(),
            'Leave type options retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreLeaveTypeRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created leave type.', type: 'array{success: true, message: string, data: LeaveTypeResource, meta: null, errors: null}')]
    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveType::class);

        return $this->created(
            new LeaveTypeResource($this->leaveTypeService->store($request->validated())),
            'Leave type created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  LeaveType  $leave_type
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A leave type.', type: 'array{success: true, message: string, data: LeaveTypeResource, meta: null, errors: null}')]
    public function show(LeaveType $leave_type): JsonResponse
    {
        $this->authorize('view', $leave_type);

        return $this->success(
            new LeaveTypeResource($this->leaveTypeService->show($leave_type)),
            'Leave type retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateLeaveTypeRequest  $request
     * @param  LeaveType  $leave_type
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated leave type.', type: 'array{success: true, message: string, data: LeaveTypeResource, meta: null, errors: null}')]
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leave_type): JsonResponse
    {
        $this->authorize('update', $leave_type);

        return $this->updated(
            new LeaveTypeResource($this->leaveTypeService->update($leave_type, $request->validated())),
            'Leave type updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  LeaveType  $leave_type
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted leave type.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(LeaveType $leave_type): JsonResponse
    {
        $this->authorize('delete', $leave_type);
        $this->leaveTypeService->destroy($leave_type);

        return $this->deleted('Leave type deleted successfully.');
    }
}
