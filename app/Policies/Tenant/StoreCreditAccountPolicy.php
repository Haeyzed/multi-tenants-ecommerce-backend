<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\StoreCreditAccount;
use App\Models\Tenant\User;

/**
 * Authorization for customer store credit wallets.
 */
class StoreCreditAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('store_credit.view');
    }

    public function view(User $user, StoreCreditAccount $account): bool
    {
        return $user->can('store_credit.view');
    }

    public function manage(User $user, StoreCreditAccount $account): bool
    {
        return $user->can('store_credit.manage');
    }
}
