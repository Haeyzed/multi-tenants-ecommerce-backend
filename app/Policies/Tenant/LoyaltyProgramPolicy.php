<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\LoyaltyProgram;
use App\Models\Tenant\User;

/**
 * Authorization for loyalty program settings.
 */
class LoyaltyProgramPolicy
{
    public function view(User $user, LoyaltyProgram $program): bool
    {
        return $user->can('loyalty.view');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('loyalty.view');
    }

    public function update(User $user, LoyaltyProgram $program): bool
    {
        return $user->can('loyalty.manage');
    }
}
