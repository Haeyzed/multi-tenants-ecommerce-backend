<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\User;

/**
 * Authorization for tenant inventory actions.
 */
class InventoryPolicy
{
    /**
     * Determine whether the user can list inventory records.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    /**
     * Determine whether the user can view an inventory record.
     */
    public function view(User $user, Inventory $inventory): bool
    {
        return $user->can('inventory.view');
    }

    /**
     * Determine whether the user can adjust inventory quantities.
     */
    public function adjust(User $user, Inventory $inventory): bool
    {
        return $user->can('inventory.adjust');
    }

    /**
     * Determine whether the user can reserve inventory.
     */
    public function reserve(User $user, Inventory $inventory): bool
    {
        return $user->can('inventory.adjust');
    }

    /**
     * Determine whether the user can release reserved inventory.
     */
    public function release(User $user, Inventory $inventory): bool
    {
        return $user->can('inventory.adjust');
    }

    /**
     * Determine whether the user can transfer inventory between warehouses.
     */
    public function transfer(User $user, Inventory $inventory): bool
    {
        return $user->can('inventory.transfer');
    }
}
