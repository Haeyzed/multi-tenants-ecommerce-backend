<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ShippingMethod;
use App\Models\Tenant\User;

/**
 * Authorization for shipping methods.
 */
class ShippingMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shipping.view');
    }

    public function view(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->can('shipping.view');
    }

    public function create(User $user): bool
    {
        return $user->can('shipping.manage');
    }

    public function update(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->can('shipping.manage');
    }

    public function delete(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->can('shipping.manage');
    }
}
