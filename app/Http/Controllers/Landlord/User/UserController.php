<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\User\IndexUserRequest;
use App\Http\Requests\Landlord\User\StoreUserRequest;
use App\Http\Requests\Landlord\User\SyncPermissionsRequest;
use App\Http\Requests\Landlord\User\SyncRolesRequest;
use App\Http\Requests\Landlord\User\UpdateUserRequest;
use App\Http\Resources\Landlord\User\UserResource;
use App\Models\Landlord\User;
use App\Services\Landlord\User\UserService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Landlord user management endpoints.
 */
class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  UserService  $userService
     */
    public function __construct(private readonly UserService $userService) {}

    /**
     * List landlord users with pagination and search.
     *
     * @param  IndexUserRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of landlord users.',
        type: 'array{success: true, message: string, data: UserResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexUserRequest $request): JsonResponse
    {
        $users = $this->userService->list($request->validated());

        return $this->success(
            UserResource::collection($users->items()),
            'Users retrieved successfully.',
            $this->paginationMeta($users),
        );
    }

    /**
     * Create a landlord user.
     *
     * @param  StoreUserRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created landlord user.',
        type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}',
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = Auth::guard('landlord')->user();
        $data = $request->safe()->except(['avatar']);

        $user = $this->userService->store($data, $request->file('avatar'), $actor);

        return $this->created(
            new UserResource($user),
            'User created successfully.',
        );
    }

    /**
     * Show a landlord user.
     *
     * @param  User  $user
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single landlord user.',
        type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}',
    )]
    public function show(User $user): JsonResponse
    {
        return $this->success(
            new UserResource($this->userService->show($user)),
            'User retrieved successfully.',
        );
    }

    /**
     * Update a landlord user.
     *
     * @param  UpdateUserRequest  $request
     * @param  User  $user
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated landlord user.',
        type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}',
    )]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = Auth::guard('landlord')->user();
        $data = $request->safe()->except(['avatar']);

        $user = $this->userService->update($user, $data, $request->file('avatar'), $actor);

        return $this->updated(
            new UserResource($user),
            'User updated successfully.',
        );
    }

    /**
     * Delete a landlord user.
     *
     * @param  User  $user
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Deleted landlord user confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(User $user): JsonResponse
    {
        $this->userService->destroy($user);

        return $this->deleted('User deleted successfully.');
    }

    /**
     * Sync roles for a landlord user.
     *
     * @param  SyncRolesRequest  $request
     * @param  User  $user
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Landlord user with synced roles.',
        type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}',
    )]
    public function syncRoles(SyncRolesRequest $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = Auth::guard('landlord')->user();

        $user = $this->userService->syncRoles(
            $user,
            $request->validated('roles'),
            $actor,
        );

        return $this->updated(
            new UserResource($user),
            'User roles synced successfully.',
        );
    }

    /**
     * Sync direct permissions for a landlord user.
     *
     * @param  SyncPermissionsRequest  $request
     * @param  User  $user
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Landlord user with synced permissions.',
        type: 'array{success: true, message: string, data: UserResource, meta: null, errors: null}',
    )]
    public function syncPermissions(SyncPermissionsRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->syncPermissions(
            $user,
            $request->validated('permissions'),
        );

        return $this->updated(
            new UserResource($user),
            'User permissions synced successfully.',
        );
    }
}
