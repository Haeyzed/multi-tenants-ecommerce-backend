<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\LoyaltyAccount;
use App\Models\Tenant\User;

/**
 * Authorization for customer loyalty accounts and their ledger.
 */
class LoyaltyAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loyalty.view');
    }

    public function view(User $user, LoyaltyAccount $account): bool
    {
        return $user->can('loyalty.view');
    }

    public function adjust(User $user, LoyaltyAccount $account): bool
    {
        return $user->can('loyalty.manage');
    }
}
