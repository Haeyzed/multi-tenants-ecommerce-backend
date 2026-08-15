<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Order;
use App\Models\Tenant\User;

/**
 * Authorization for admin order management.
 */
class OrderPolicy
{
    /**
     * Determine whether the user can list orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    /**
     * Determine whether the user can view an order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.show') || $user->can('orders.view');
    }

    /**
     * Determine whether the user can update order status.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.update');
    }

    /**
     * Determine whether the user can cancel an order.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->can('orders.cancel') || $user->can('orders.update');
    }
}
