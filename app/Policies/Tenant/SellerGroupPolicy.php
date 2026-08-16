<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\SellerGroup;
use App\Models\Tenant\User;

/**
 * Authorization for tenant seller group actions.
 */
class SellerGroupPolicy
{
    /**
     * Determine whether the user can list seller groups.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('seller_groups.view');
    }

    /**
     * Determine whether the user can view a seller group.
     */
    public function view(User $user, SellerGroup $sellerGroup): bool
    {
        return $user->can('seller_groups.show') || $user->can('seller_groups.view');
    }

    /**
     * Determine whether the user can create seller groups.
     */
    public function create(User $user): bool
    {
        return $user->can('seller_groups.create');
    }

    /**
     * Determine whether the user can update a seller group.
     */
    public function update(User $user, SellerGroup $sellerGroup): bool
    {
        return $user->can('seller_groups.update');
    }

    /**
     * Determine whether the user can delete a seller group.
     */
    public function delete(User $user, SellerGroup $sellerGroup): bool
    {
        return $user->can('seller_groups.delete');
    }
}
