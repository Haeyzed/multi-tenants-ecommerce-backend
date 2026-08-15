<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Account;
use App\Models\Tenant\User;

/**
 * Authorization for chart of accounts.
 */
class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view');
    }

    public function view(User $user, Account $account): bool
    {
        return $user->can('accounting.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('accounting.manage');
    }
}
