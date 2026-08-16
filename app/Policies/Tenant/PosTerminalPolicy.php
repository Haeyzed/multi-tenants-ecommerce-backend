<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\User;

/**
 * Authorization for POS terminals.
 */
class PosTerminalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.view') || $user->can('pos.terminals.manage');
    }

    public function view(User $user, PosTerminal $terminal): bool
    {
        return $user->can('pos.view') || $user->can('pos.terminals.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('pos.terminals.manage');
    }

    public function update(User $user, PosTerminal $terminal): bool
    {
        return $user->can('pos.terminals.manage');
    }

    public function delete(User $user, PosTerminal $terminal): bool
    {
        return $user->can('pos.terminals.manage');
    }

    public function openSession(User $user, PosTerminal $terminal): bool
    {
        return $user->can('pos.session.open');
    }
}
