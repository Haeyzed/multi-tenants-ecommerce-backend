<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\User;

/**
 * Authorization for tenant product catalog actions.
 */
class ProductPolicy
{
    /**
     * Determine whether the user can list products.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    /**
     * Determine whether the user can view a product.
     */
    public function view(User $user, Product $product): bool
    {
        return $user->can('products.show') || $user->can('products.view');
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    /**
     * Determine whether the user can update a product.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update');
    }

    /**
     * Determine whether the user can delete a product.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete');
    }
}
