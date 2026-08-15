<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\SellerCommission;
use App\Models\Tenant\User;

/**
 * Authorization for marketplace commissions.
 */
class SellerCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('commissions.view');
    }

    public function view(User $user, SellerCommission $commission): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $commission->seller_id
                && $user->can('commissions.view');
        }

        return $user->can('commissions.view');
    }

    public function manage(User $user, SellerCommission $commission): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $commission->seller_id
                && $user->can('commissions.manage');
        }

        return $user->can('commissions.manage');
    }
}
