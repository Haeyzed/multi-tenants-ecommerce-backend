<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\User;

/**
 * Authorization for tenant product attribute actions.
 */
class ProductAttributePolicy
{
    /**
     * Determine whether the user can list attributes.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('attributes.view');
    }

    /**
     * Determine whether the user can view an attribute.
     */
    public function view(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('attributes.show') || $user->can('attributes.view');
    }

    /**
     * Determine whether the user can create attributes.
     */
    public function create(User $user): bool
    {
        return $user->can('attributes.create');
    }

    /**
     * Determine whether the user can update an attribute.
     */
    public function update(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('attributes.update');
    }

    /**
     * Determine whether the user can delete an attribute.
     */
    public function delete(User $user, ProductAttribute $attribute): bool
    {
        return $user->can('attributes.delete');
    }
}
