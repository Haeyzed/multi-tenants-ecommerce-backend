<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\User;

/**
 * Authorization for customer admin actions.
 */
class CustomerPolicy
{
    /**
     * Determine whether the user can list customers.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    /**
     * Determine whether the user can view a customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.show') || $user->can('customers.view');
    }

    /**
     * Determine whether the user can update a customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update');
    }

    /**
     * Determine whether the user can delete a customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete');
    }
}
