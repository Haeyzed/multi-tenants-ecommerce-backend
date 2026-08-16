<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerCommission;
use App\Models\Tenant\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for marketplace commissions.
 */
class SellerCommissionPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        if ($actor instanceof Seller) {
            return true;
        }

        return $actor instanceof User && $actor->can('commissions.view');
    }

    public function view(Authenticatable $actor, SellerCommission $commission): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $commission->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('commissions.view');
        }

        return false;
    }

    public function manage(Authenticatable $actor, SellerCommission $commission): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $commission->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('commissions.manage');
        }

        return false;
    }
}
