<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Unit;
use App\Models\Tenant\User;

/**
 * Authorization for tenant measurement unit actions.
 */
class UnitPolicy
{
    /**
     * Determine whether the user can list units.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('units.view');
    }

    /**
     * Determine whether the user can view a unit.
     */
    public function view(User $user, Unit $unit): bool
    {
        return $user->can('units.show') || $user->can('units.view');
    }

    /**
     * Determine whether the user can create units.
     */
    public function create(User $user): bool
    {
        return $user->can('units.create');
    }

    /**
     * Determine whether the user can update a unit.
     */
    public function update(User $user, Unit $unit): bool
    {
        return $user->can('units.update');
    }

    /**
     * Determine whether the user can delete a unit.
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->can('units.delete');
    }
}
