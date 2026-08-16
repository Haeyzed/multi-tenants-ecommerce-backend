<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\OrderReturn;
use App\Models\Tenant\Seller;
use App\Models\Tenant\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for order returns (staff / seller).
 */
class OrderReturnPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        if ($actor instanceof Seller) {
            return true;
        }

        return $actor instanceof User && $actor->can('returns.view');
    }

    public function view(Authenticatable $actor, OrderReturn $return): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $return->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('returns.view');
        }

        return false;
    }

    public function approve(Authenticatable $actor, OrderReturn $return): bool
    {
        return $actor instanceof User && $actor->can('returns.approve');
    }

    public function reject(Authenticatable $actor, OrderReturn $return): bool
    {
        return $actor instanceof User && $actor->can('returns.reject');
    }

    public function inspect(Authenticatable $actor, OrderReturn $return): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $return->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('returns.inspect');
        }

        return false;
    }

    public function complete(Authenticatable $actor, OrderReturn $return): bool
    {
        return $actor instanceof User && $actor->can('returns.complete');
    }
}
