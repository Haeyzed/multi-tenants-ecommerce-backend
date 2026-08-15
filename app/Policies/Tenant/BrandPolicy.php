<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Brand;
use App\Models\Tenant\User;

/**
 * Authorization for tenant brand catalog actions.
 */
class BrandPolicy
{
    /**
     * Determine whether the user can list brands.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('brands.view');
    }

    /**
     * Determine whether the user can view a brand.
     */
    public function view(User $user, Brand $brand): bool
    {
        return $user->can('brands.show') || $user->can('brands.view');
    }

    /**
     * Determine whether the user can create brands.
     */
    public function create(User $user): bool
    {
        return $user->can('brands.create');
    }

    /**
     * Determine whether the user can update a brand.
     */
    public function update(User $user, Brand $brand): bool
    {
        return $user->can('brands.update');
    }

    /**
     * Determine whether the user can delete a brand.
     */
    public function delete(User $user, Brand $brand): bool
    {
        return $user->can('brands.delete');
    }
}
