<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Tax;
use App\Models\Tenant\User;

/**
 * Authorization for tax configuration.
 */
class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('taxes.view');
    }

    public function view(User $user, Tax $tax): bool
    {
        return $user->can('taxes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('taxes.create');
    }

    public function update(User $user, Tax $tax): bool
    {
        return $user->can('taxes.update');
    }

    public function delete(User $user, Tax $tax): bool
    {
        return $user->can('taxes.delete');
    }
}
