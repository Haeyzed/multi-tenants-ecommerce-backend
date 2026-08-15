<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\User;

/**
 * Authorization for purchase orders / procurement.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('procurement.view');
    }

    public function create(User $user): bool
    {
        return $user->can('procurement.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('procurement.update');
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('procurement.approve');
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('procurement.receive');
    }
}
