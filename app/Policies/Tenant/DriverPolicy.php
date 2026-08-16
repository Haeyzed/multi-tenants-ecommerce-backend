<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Driver;
use App\Models\Tenant\User;

/**
 * Authorization for driver admin actions.
 */
class DriverPolicy
{
    /**
     * Determine whether the user can list drivers.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('drivers.view');
    }

    /**
     * Determine whether the user can view a driver.
     */
    public function view(User $user, Driver $driver): bool
    {
        return $user->can('drivers.show') || $user->can('drivers.view');
    }

    /**
     * Determine whether the user can create drivers.
     */
    public function create(User $user): bool
    {
        return $user->can('drivers.create');
    }

    /**
     * Determine whether the user can update a driver.
     */
    public function update(User $user, Driver $driver): bool
    {
        return $user->can('drivers.update');
    }

    /**
     * Determine whether the user can delete a driver.
     */
    public function delete(User $user, Driver $driver): bool
    {
        return $user->can('drivers.delete');
    }
}
