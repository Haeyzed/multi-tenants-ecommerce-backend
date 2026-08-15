<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\SellerOrder;
use App\Models\Tenant\User;

/**
 * Authorization for seller sub-orders.
 */
class SellerOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('seller_orders.view');
    }

    public function view(User $user, SellerOrder $sellerOrder): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $sellerOrder->seller_id
                && $user->can('seller_orders.view');
        }

        return $user->can('seller_orders.view');
    }

    public function manage(User $user, SellerOrder $sellerOrder): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $sellerOrder->seller_id
                && $user->can('seller_orders.manage');
        }

        return $user->can('seller_orders.manage');
    }
}
