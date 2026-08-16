<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerPayout;
use App\Models\Tenant\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for seller payouts.
 */
class SellerPayoutPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        if ($actor instanceof Seller) {
            return true;
        }

        return $actor instanceof User && $actor->can('payouts.view');
    }

    public function view(Authenticatable $actor, SellerPayout $payout): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $payout->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('payouts.view');
        }

        return false;
    }

    public function create(Authenticatable $actor): bool
    {
        if ($actor instanceof Seller) {
            return true;
        }

        return $actor instanceof User && $actor->can('payouts.manage');
    }

    public function manage(Authenticatable $actor, SellerPayout $payout): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $payout->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('payouts.manage');
        }

        return false;
    }
}
