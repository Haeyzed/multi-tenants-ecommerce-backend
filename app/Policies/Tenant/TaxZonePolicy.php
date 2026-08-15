<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\TaxZone;
use App\Models\Tenant\User;

/**
 * Authorization for tax zones.
 */
class TaxZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('taxes.view');
    }

    public function view(User $user, TaxZone $taxZone): bool
    {
        return $user->can('taxes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('taxes.create');
    }

    public function update(User $user, TaxZone $taxZone): bool
    {
        return $user->can('taxes.update');
    }

    public function delete(User $user, TaxZone $taxZone): bool
    {
        return $user->can('taxes.delete');
    }
}
