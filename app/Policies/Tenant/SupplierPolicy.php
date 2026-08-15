<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;

/**
 * Authorization for suppliers.
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.show') || $user->can('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.delete');
    }
}
