<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Category;
use App\Models\Tenant\User;

/**
 * Authorization for tenant category catalog actions.
 */
class CategoryPolicy
{
    /**
     * Determine whether the user can list categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('categories.view');
    }

    /**
     * Determine whether the user can view a category.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->can('categories.show') || $user->can('categories.view');
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->can('categories.create');
    }

    /**
     * Determine whether the user can update a category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.update');
    }

    /**
     * Determine whether the user can delete a category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories.delete');
    }
}
