<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\PosSession;
use App\Models\Tenant\User;

/**
 * Authorization for POS sessions.
 */
class PosSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.view');
    }

    public function view(User $user, PosSession $session): bool
    {
        return $user->can('pos.view');
    }

    public function close(User $user, PosSession $session): bool
    {
        return $user->can('pos.session.close');
    }

    public function cashIn(User $user, PosSession $session): bool
    {
        return $user->can('pos.cash_in');
    }

    public function cashOut(User $user, PosSession $session): bool
    {
        return $user->can('pos.cash_out');
    }

    public function sell(User $user, PosSession $session): bool
    {
        return $user->can('pos.sell');
    }
}
