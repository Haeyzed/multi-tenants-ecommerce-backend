<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ProductTag;
use App\Models\Tenant\User;

/**
 * Authorization for tenant product tag actions.
 */
class ProductTagPolicy
{
    /**
     * Determine whether the user can list tags.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tags.view');
    }

    /**
     * Determine whether the user can view a tag.
     */
    public function view(User $user, ProductTag $tag): bool
    {
        return $user->can('tags.show') || $user->can('tags.view');
    }

    /**
     * Determine whether the user can create tags.
     */
    public function create(User $user): bool
    {
        return $user->can('tags.create');
    }

    /**
     * Determine whether the user can update a tag.
     */
    public function update(User $user, ProductTag $tag): bool
    {
        return $user->can('tags.update');
    }

    /**
     * Determine whether the user can delete a tag.
     */
    public function delete(User $user, ProductTag $tag): bool
    {
        return $user->can('tags.delete');
    }
}
