<?php

declare(strict_types=1);

namespace App\Services\Tenant\User;

use App\Enums\Media\MediaCollection;
use App\Events\UserCreated;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Services\Landlord\Feature\UsageLimiter;
use App\Services\Media\MediaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant user CRUD and role/permission assignment.
 */
class UserService
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly UsageLimiter $usageLimiter,
    ) {}

    /**
     * Retrieve a paginated list of tenant users.
     *
     * @param  array{search?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles', 'permissions'])
            ->filter($params)
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * Create a new tenant user.
     *
     * @param  array{first_name: string, last_name: string, email: string, phone?: string|null, password: string, roles?: list<string>, permissions?: list<string>}  $data
     */
    public function store(array $data, ?UploadedFile $avatar = null): User
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant && $tenant->activeSubscription() !== null) {
            $this->usageLimiter->assertCanCreate('users', $tenant);
        }

        $roles = $data['roles'] ?? [];
        $permissions = $data['permissions'] ?? [];
        unset($data['roles'], $data['permissions']);

        $user = User::query()->create($data);

        if ($roles !== []) {
            $user->syncRoles($roles);
        }

        if ($permissions !== []) {
            $user->syncPermissions($permissions);
        }

        if ($avatar !== null) {
            $this->mediaService->replace($user, $avatar, MediaCollection::Avatar);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $user->load(['roles', 'permissions']);

        event(new UserCreated($user));

        return $user;
    }

    /**
     * Retrieve a single tenant user.
     */
    public function show(User $user): User
    {
        return $user->load(['roles', 'permissions']);
    }

    /**
     * Update a tenant user.
     *
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string|null, password?: string, roles?: list<string>, permissions?: list<string>}  $data
     */
    public function update(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $roles = $data['roles'] ?? null;
        $permissions = $data['permissions'] ?? null;
        unset($data['roles'], $data['permissions']);

        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $user->fill($data);
        $user->save();

        if (is_array($roles)) {
            $user->syncRoles($roles);
        }

        if (is_array($permissions)) {
            $user->syncPermissions($permissions);
        }

        if ($avatar !== null) {
            $this->mediaService->replace($user, $avatar, MediaCollection::Avatar);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh(['roles', 'permissions']) ?? $user;
    }

    /**
     * Delete a tenant user.
     */
    public function destroy(User $user): void
    {
        $user->tokens()->delete();
        $this->mediaService->removeCollection($user, MediaCollection::Avatar);
        $user->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Sync roles for a tenant user.
     *
     * @param  list<string>  $roles
     *
     * @throws AuthorizationException
     */
    public function syncRoles(User $user, array $roles, User $actor): User
    {
        if (in_array('super-admin', $roles, true) && ! $actor->hasRole('super-admin')) {
            throw new AuthorizationException('Only super-admins can assign the super-admin role.');
        }

        $user->syncRoles($roles);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh(['roles', 'permissions']) ?? $user;
    }

    /**
     * Sync direct permissions for a tenant user.
     *
     * @param  list<string>  $permissions
     */
    public function syncPermissions(User $user, array $permissions): User
    {
        $user->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh(['roles', 'permissions']) ?? $user;
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
