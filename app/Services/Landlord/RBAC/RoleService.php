<?php

declare(strict_types=1);

namespace App\Services\Landlord\RBAC;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Landlord role management.
 */
class RoleService
{
    /**
     * Guard name for landlord Spatie roles.
     */
    private const string GUARD = 'landlord';

    /**
     * Retrieve a paginated list of landlord roles.
     *
     * @param  array{search?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Role>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Role::query()
            ->where('guard_name', self::GUARD)
            ->with('permissions')
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Create a landlord role.
     *
     * @param  array{name: string, permissions?: list<string>}  $data
     * @return Role
     */
    public function store(array $data): Role
    {
        $role = Role::query()->create([
            'name' => $data['name'],
            'guard_name' => self::GUARD,
        ]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->load('permissions');
    }

    /**
     * Retrieve a single landlord role.
     *
     * @param  Role  $role
     * @return Role
     */
    public function show(Role $role): Role
    {
        return $role->load('permissions');
    }

    /**
     * Update a landlord role.
     *
     * @param  Role  $role
     * @param  array{name?: string, permissions?: list<string>}  $data
     * @return Role
     */
    public function update(Role $role, array $data): Role
    {
        if (isset($data['name'])) {
            $role->name = $data['name'];
            $role->save();
        }

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh('permissions') ?? $role;
    }

    /**
     * Delete a landlord role.
     *
     * @param  Role  $role
     * @return void
     */
    public function destroy(Role $role): void
    {
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Sync permissions for a landlord role.
     *
     * @param  Role  $role
     * @param  list<string>  $permissions
     * @return Role
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh('permissions') ?? $role;
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
