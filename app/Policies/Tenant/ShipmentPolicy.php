<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Shipment;
use App\Models\Tenant\User;

/**
 * Authorization for shipments.
 */
class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shipments.view');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('shipments.manage');
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.manage');
    }
}
