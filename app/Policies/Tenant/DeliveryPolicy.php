<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\User;

/**
 * Authorization for delivery actions across staff, drivers, and customers.
 */
class DeliveryPolicy
{
    /**
     * Determine whether the user can list deliveries.
     */
    public function viewAny(User|Driver $user): bool
    {
        if ($user instanceof Driver) {
            return true;
        }

        return $user->can('deliveries.view') || $user->can('deliveries.manage');
    }

    /**
     * Determine whether the actor can view a delivery.
     */
    public function view(User|Driver|Customer $user, Delivery $delivery): bool
    {
        if ($user instanceof Driver) {
            return $delivery->driver_id === $user->id;
        }

        if ($user instanceof Customer) {
            return $delivery->order?->customer_id === $user->id
                || $delivery->order()->where('customer_id', $user->id)->exists();
        }

        return $user->can('deliveries.view') || $user->can('deliveries.manage');
    }

    /**
     * Determine whether the user can create deliveries.
     */
    public function create(User $user): bool
    {
        return $user->can('deliveries.manage');
    }

    /**
     * Determine whether the user can manage (assign/cancel/etc.) a delivery.
     */
    public function update(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.manage');
    }

    /**
     * Determine whether a driver can act on their assigned delivery.
     */
    public function drive(Driver $driver, Delivery $delivery): bool
    {
        return $delivery->driver_id === $driver->id;
    }
}
