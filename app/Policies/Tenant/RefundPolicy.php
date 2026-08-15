<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Refund;
use App\Models\Tenant\User;

/**
 * Authorization for payment refunds.
 */
class RefundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('refunds.view');
    }

    public function view(User $user, Refund $refund): bool
    {
        return $user->can('refunds.view');
    }

    public function create(User $user): bool
    {
        return $user->can('refunds.create');
    }

    public function process(User $user, Refund $refund): bool
    {
        return $user->can('refunds.process');
    }
}
