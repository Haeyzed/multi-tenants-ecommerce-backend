<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\User;

/**
 * Authorization for tenant product variant actions.
 */
class ProductVariantPolicy
{
    /**
     * Determine whether the user can list variants.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('variants.view');
    }

    /**
     * Determine whether the user can view a variant.
     */
    public function view(User $user, ProductVariant $variant): bool
    {
        return $user->can('variants.show') || $user->can('variants.view');
    }

    /**
     * Determine whether the user can create variants.
     */
    public function create(User $user): bool
    {
        return $user->can('variants.create');
    }

    /**
     * Determine whether the user can update a variant.
     */
    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->can('variants.update');
    }

    /**
     * Determine whether the user can delete a variant.
     */
    public function delete(User $user, ProductVariant $variant): bool
    {
        return $user->can('variants.delete');
    }
}
