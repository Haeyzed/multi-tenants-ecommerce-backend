<?php

declare(strict_types=1);

namespace App\Services\Landlord\RBAC;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Landlord permission management.
 */
class PermissionService
{
    /**
     * Guard name for landlord Spatie permissions.
     */
    private const string GUARD = 'landlord';

    /**
     * Retrieve a paginated list of landlord permissions.
     *
     * @param  array{search?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Permission>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Permission::query()
            ->where('guard_name', self::GUARD)
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Create a landlord permission.
     *
     * @param  array{name: string}  $data
     * @return Permission
     */
    public function store(array $data): Permission
    {
        $permission = Permission::query()->create([
            'name' => $data['name'],
            'guard_name' => self::GUARD,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }

    /**
     * Retrieve a single landlord permission.
     *
     * @param  Permission  $permission
     * @return Permission
     */
    public function show(Permission $permission): Permission
    {
        return $permission;
    }

    /**
     * Update a landlord permission.
     *
     * @param  Permission  $permission
     * @param  array{name: string}  $data
     * @return Permission
     */
    public function update(Permission $permission, array $data): Permission
    {
        $permission->name = $data['name'];
        $permission->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission->fresh() ?? $permission;
    }

    /**
     * Delete a landlord permission.
     *
     * @param  Permission  $permission
     * @return void
     */
    public function destroy(Permission $permission): void
    {
        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
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
