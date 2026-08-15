<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\RBAC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RBAC\IndexRoleRequest;
use App\Http\Requests\Tenant\RBAC\StoreRoleRequest;
use App\Http\Requests\Tenant\RBAC\SyncRolePermissionsRequest;
use App\Http\Requests\Tenant\RBAC\UpdateRoleRequest;
use App\Http\Resources\Tenant\RBAC\RoleResource;
use App\Services\Tenant\RBAC\RoleService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Tenant role management endpoints.
 */
class RoleController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly RoleService $roleService) {}

    /**
     * List tenant roles with pagination and search.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of tenant roles.',
        type: 'array{success: true, message: string, data: RoleResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexRoleRequest $request): JsonResponse
    {
        $roles = $this->roleService->list($request->validated());

        return $this->success(
            RoleResource::collection($roles->items()),
            'Roles retrieved successfully.',
            $this->paginationMeta($roles),
        );
    }

    /**
     * Create a tenant role.
     */
    #[Response(
        status: 201,
        description: 'Created tenant role.',
        type: 'array{success: true, message: string, data: RoleResource, meta: null, errors: null}',
    )]
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->store($request->validated());

        return $this->created(
            new RoleResource($role),
            'Role created successfully.',
        );
    }

    /**
     * Show a tenant role.
     */
    #[Response(
        status: 200,
        description: 'A single tenant role.',
        type: 'array{success: true, message: string, data: RoleResource, meta: null, errors: null}',
    )]
    public function show(Role $role): JsonResponse
    {
        return $this->success(
            new RoleResource($this->roleService->show($role)),
            'Role retrieved successfully.',
        );
    }

    /**
     * Update a tenant role.
     */
    #[Response(
        status: 200,
        description: 'Updated tenant role.',
        type: 'array{success: true, message: string, data: RoleResource, meta: null, errors: null}',
    )]
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->update($role, $request->validated());

        return $this->updated(
            new RoleResource($role),
            'Role updated successfully.',
        );
    }

    /**
     * Delete a tenant role.
     */
    #[Response(
        status: 200,
        description: 'Deleted tenant role confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->destroy($role);

        return $this->deleted('Role deleted successfully.');
    }

    /**
     * Sync permissions for a tenant role.
     */
    #[Response(
        status: 200,
        description: 'Tenant role with synced permissions.',
        type: 'array{success: true, message: string, data: RoleResource, meta: null, errors: null}',
    )]
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->syncPermissions(
            $role,
            $request->validated('permissions'),
        );

        return $this->updated(
            new RoleResource($role),
            'Role permissions synced successfully.',
        );
    }
}
