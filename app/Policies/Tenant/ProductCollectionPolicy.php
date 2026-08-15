<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ProductCollection;
use App\Models\Tenant\User;

/**
 * Authorization for tenant product collection actions.
 */
class ProductCollectionPolicy
{
    /**
     * Determine whether the user can list collections.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('collections.view');
    }

    /**
     * Determine whether the user can view a collection.
     */
    public function view(User $user, ProductCollection $collection): bool
    {
        return $user->can('collections.show') || $user->can('collections.view');
    }

    /**
     * Determine whether the user can create collections.
     */
    public function create(User $user): bool
    {
        return $user->can('collections.create');
    }

    /**
     * Determine whether the user can update a collection.
     */
    public function update(User $user, ProductCollection $collection): bool
    {
        return $user->can('collections.update');
    }

    /**
     * Determine whether the user can delete a collection.
     */
    public function delete(User $user, ProductCollection $collection): bool
    {
        return $user->can('collections.delete');
    }
}
