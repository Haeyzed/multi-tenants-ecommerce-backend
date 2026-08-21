<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\RBAC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\RBAC\IndexRoleRequest;
use App\Http\Requests\Landlord\RBAC\StoreRoleRequest;
use App\Http\Requests\Landlord\RBAC\SyncRolePermissionsRequest;
use App\Http\Requests\Landlord\RBAC\UpdateRoleRequest;
use App\Http\Resources\Landlord\RBAC\RoleResource;
use App\Services\Landlord\RBAC\RoleService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Landlord role management endpoints.
 */
class RoleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  RoleService  $roleService
     */
    public function __construct(private readonly RoleService $roleService) {}

    /**
     * List landlord roles with pagination and search.
     *
     * @param  IndexRoleRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of landlord roles.',
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
     * Create a landlord role.
     *
     * @param  StoreRoleRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created landlord role.',
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
     * Show a landlord role.
     *
     * @param  Role  $role
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single landlord role.',
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
     * Update a landlord role.
     *
     * @param  UpdateRoleRequest  $request
     * @param  Role  $role
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated landlord role.',
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
     * Delete a landlord role.
     *
     * @param  Role  $role
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Deleted landlord role confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->destroy($role);

        return $this->deleted('Role deleted successfully.');
    }

    /**
     * Sync permissions for a landlord role.
     *
     * @param  SyncRolePermissionsRequest  $request
     * @param  Role  $role
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Landlord role with synced permissions.',
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
