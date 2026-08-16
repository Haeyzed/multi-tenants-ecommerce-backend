<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerOrder;
use App\Models\Tenant\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for seller sub-orders.
 */
class SellerOrderPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        if ($actor instanceof Seller) {
            return true;
        }

        return $actor instanceof User && $actor->can('seller_orders.view');
    }

    public function view(Authenticatable $actor, SellerOrder $sellerOrder): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $sellerOrder->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('seller_orders.view');
        }

        return false;
    }

    public function manage(Authenticatable $actor, SellerOrder $sellerOrder): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $sellerOrder->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('seller_orders.manage');
        }

        return false;
    }
}
