<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\OrderReturn;
use App\Models\Tenant\User;

/**
 * Authorization for order returns (staff / seller).
 */
class OrderReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('returns.view');
    }

    public function view(User $user, OrderReturn $return): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $return->seller_id
                && $user->can('returns.view');
        }

        return $user->can('returns.view');
    }

    public function approve(User $user, OrderReturn $return): bool
    {
        return $user->can('returns.approve') && ! $user->isSellerUser();
    }

    public function reject(User $user, OrderReturn $return): bool
    {
        return $user->can('returns.reject') && ! $user->isSellerUser();
    }

    public function inspect(User $user, OrderReturn $return): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $return->seller_id
                && $user->can('returns.inspect');
        }

        return $user->can('returns.inspect');
    }

    public function complete(User $user, OrderReturn $return): bool
    {
        return $user->can('returns.complete') && ! $user->isSellerUser();
    }
}
