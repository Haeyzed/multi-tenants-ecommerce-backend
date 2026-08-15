<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ProductBadge;
use App\Models\Tenant\User;

/**
 * Authorization for tenant product badge actions.
 */
class ProductBadgePolicy
{
    /**
     * Determine whether the user can list badges.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('badges.view');
    }

    /**
     * Determine whether the user can view a badge.
     */
    public function view(User $user, ProductBadge $badge): bool
    {
        return $user->can('badges.show') || $user->can('badges.view');
    }

    /**
     * Determine whether the user can create badges.
     */
    public function create(User $user): bool
    {
        return $user->can('badges.create');
    }

    /**
     * Determine whether the user can update a badge.
     */
    public function update(User $user, ProductBadge $badge): bool
    {
        return $user->can('badges.update');
    }

    /**
     * Determine whether the user can delete a badge.
     */
    public function delete(User $user, ProductBadge $badge): bool
    {
        return $user->can('badges.delete');
    }
}
