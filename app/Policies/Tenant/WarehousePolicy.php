<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;

/**
 * Authorization for tenant warehouse catalog actions.
 */
class WarehousePolicy
{
    /**
     * Determine whether the user can list warehouses.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('warehouses.view');
    }

    /**
     * Determine whether the user can view a warehouse.
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.show') || $user->can('warehouses.view');
    }

    /**
     * Determine whether the user can create warehouses.
     */
    public function create(User $user): bool
    {
        return $user->can('warehouses.create');
    }

    /**
     * Determine whether the user can update a warehouse.
     */
    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.update');
    }

    /**
     * Determine whether the user can delete a warehouse.
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.delete');
    }
}
