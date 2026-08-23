<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexDepartmentRequest;
use App\Http\Requests\Tenant\HR\StoreDepartmentRequest;
use App\Http\Requests\Tenant\HR\UpdateDepartmentRequest;
use App\Http\Resources\Tenant\HR\DepartmentResource;
use App\Models\Tenant\HR\Department;
use App\Services\Tenant\HR\DepartmentService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant HR department endpoints.
 */
#[Group('HR')]
class DepartmentController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  DepartmentService  $departmentService
     */
    public function __construct(private readonly DepartmentService $departmentService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexDepartmentRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated departments.', type: 'array{success: true, message: string, data: DepartmentResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexDepartmentRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $departments = $this->departmentService->list($request->validated());

        return $this->success(
            DepartmentResource::collection($departments->items()),
            'Departments retrieved successfully.',
            $this->paginationMeta($departments),
        );
    }

    /**
     * Return options for select inputs.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Department options.', type: ApiResponseSchema::OPTIONS)]
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        return $this->success(
            $this->departmentService->options(),
            'Department options retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreDepartmentRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created department.', type: 'array{success: true, message: string, data: DepartmentResource, meta: null, errors: null}')]
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        return $this->created(
            new DepartmentResource($this->departmentService->store($request->validated())),
            'Department created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Department  $department
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A department.', type: 'array{success: true, message: string, data: DepartmentResource, meta: null, errors: null}')]
    public function show(Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        return $this->success(
            new DepartmentResource($this->departmentService->show($department)),
            'Department retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateDepartmentRequest  $request
     * @param  Department  $department
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated department.', type: 'array{success: true, message: string, data: DepartmentResource, meta: null, errors: null}')]
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        return $this->updated(
            new DepartmentResource($this->departmentService->update($department, $request->validated())),
            'Department updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  Department  $department
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted department.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);
        $this->departmentService->destroy($department);

        return $this->deleted('Department deleted successfully.');
    }
}
