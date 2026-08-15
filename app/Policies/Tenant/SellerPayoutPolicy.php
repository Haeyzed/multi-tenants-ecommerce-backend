<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\SellerPayout;
use App\Models\Tenant\User;

/**
 * Authorization for seller payouts.
 */
class SellerPayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payouts.view');
    }

    public function view(User $user, SellerPayout $payout): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $payout->seller_id
                && $user->can('payouts.view');
        }

        return $user->can('payouts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payouts.manage');
    }

    public function manage(User $user, SellerPayout $payout): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $payout->seller_id
                && $user->can('payouts.manage');
        }

        return $user->can('payouts.manage');
    }
}
