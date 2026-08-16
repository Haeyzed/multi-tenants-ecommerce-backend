<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\User;

/**
 * Authorization for tenant customer group actions.
 */
class CustomerGroupPolicy
{
    /**
     * Determine whether the user can list customer groups.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('customer_groups.view');
    }

    /**
     * Determine whether the user can view a customer group.
     */
    public function view(User $user, CustomerGroup $customerGroup): bool
    {
        return $user->can('customer_groups.show') || $user->can('customer_groups.view');
    }

    /**
     * Determine whether the user can create customer groups.
     */
    public function create(User $user): bool
    {
        return $user->can('customer_groups.create');
    }

    /**
     * Determine whether the user can update a customer group.
     */
    public function update(User $user, CustomerGroup $customerGroup): bool
    {
        return $user->can('customer_groups.update');
    }

    /**
     * Determine whether the user can delete a customer group.
     */
    public function delete(User $user, CustomerGroup $customerGroup): bool
    {
        return $user->can('customer_groups.delete');
    }
}
