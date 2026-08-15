<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\RBAC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RBAC\IndexPermissionRequest;
use App\Http\Requests\Tenant\RBAC\StorePermissionRequest;
use App\Http\Requests\Tenant\RBAC\UpdatePermissionRequest;
use App\Http\Resources\Tenant\RBAC\PermissionResource;
use App\Services\Tenant\RBAC\PermissionService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * Tenant permission management endpoints.
 */
class PermissionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly PermissionService $permissionService) {}

    /**
     * List tenant permissions with pagination and search.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of tenant permissions.',
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
     * Create a tenant permission.
     */
    #[Response(
        status: 201,
        description: 'Created tenant permission.',
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
     * Show a tenant permission.
     */
    #[Response(
        status: 200,
        description: 'A single tenant permission.',
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
     * Update a tenant permission.
     */
    #[Response(
        status: 200,
        description: 'Updated tenant permission.',
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
     * Delete a tenant permission.
     */
    #[Response(
        status: 200,
        description: 'Deleted tenant permission confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Permission $permission): JsonResponse
    {
        $this->permissionService->destroy($permission);

        return $this->deleted('Permission deleted successfully.');
    }
}
