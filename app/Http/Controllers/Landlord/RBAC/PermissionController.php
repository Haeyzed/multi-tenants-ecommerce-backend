<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\RBAC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\RBAC\IndexPermissionRequest;
use App\Http\Requests\Landlord\RBAC\StorePermissionRequest;
use App\Http\Requests\Landlord\RBAC\UpdatePermissionRequest;
use App\Http\Resources\Landlord\RBAC\PermissionResource;
use App\Services\Landlord\RBAC\PermissionService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * Landlord permission management endpoints.
 */
class PermissionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly PermissionService $permissionService) {}

    /**
     * List landlord permissions with pagination and search.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of landlord permissions.',
        type: 'array{success: true, message: string, data: PermissionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexPermissionRequest $request): JsonResponse
    {
        $permissions = $this->permissionService->list($request->validated());

        return $this->success(
            PermissionResource::collection($permissions->items()),
            'Permissions retrieved successfully.',
            $this->paginationMeta($permissions),
        );
    }

    /**
     * Create a landlord permission.
     */
    #[Response(
        status: 201,
        description: 'Created landlord permission.',
        type: 'array{success: true, message: string, data: PermissionResource, meta: null, errors: null}',
    )]
    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->permissionService->store($request->validated());

        return $this->created(
            new PermissionResource($permission),
            'Permission created successfully.',
        );
    }

    /**
     * Show a landlord permission.
     */
    #[Response(
        status: 200,
        description: 'A single landlord permission.',
        type: 'array{success: true, message: string, data: PermissionResource, meta: null, errors: null}',
    )]
    public function show(Permission $permission): JsonResponse
    {
        return $this->success(
            new PermissionResource($this->permissionService->show($permission)),
            'Permission retrieved successfully.',
        );
    }

    /**
     * Update a landlord permission.
     */
    #[Response(
        status: 200,
        description: 'Updated landlord permission.',
        type: 'array{success: true, message: string, data: PermissionResource, meta: null, errors: null}',
    )]
    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->permissionService->update($permission, $request->validated());

        return $this->updated(
            new PermissionResource($permission),
            'Permission updated successfully.',
        );
    }

    /**
     * Delete a landlord permission.
     */
    #[Response(
        status: 200,
        description: 'Deleted landlord permission confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Permission $permission): JsonResponse
    {
        $this->permissionService->destroy($permission);

        return $this->deleted('Permission deleted successfully.');
    }
}
