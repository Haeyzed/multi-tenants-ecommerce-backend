<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ProductOption;
use App\Models\Tenant\User;

/**
 * Authorization for tenant product option actions.
 */
class ProductOptionPolicy
{
    /**
     * Determine whether the user can list options.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('options.view');
    }

    /**
     * Determine whether the user can view an option.
     */
    public function view(User $user, ProductOption $option): bool
    {
        return $user->can('options.show') || $user->can('options.view');
    }

    /**
     * Determine whether the user can create options.
     */
    public function create(User $user): bool
    {
        return $user->can('options.create');
    }

    /**
     * Determine whether the user can update an option.
     */
    public function update(User $user, ProductOption $option): bool
    {
        return $user->can('options.update');
    }

    /**
     * Determine whether the user can delete an option.
     */
    public function delete(User $user, ProductOption $option): bool
    {
        return $user->can('options.delete');
    }
}
