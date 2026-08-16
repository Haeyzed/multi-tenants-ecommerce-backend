<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\FlashSale;
use App\Models\Tenant\User;

/**
 * Authorization for flash sales.
 */
class FlashSalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('flash_sales.view');
    }

    public function view(User $user, FlashSale $flashSale): bool
    {
        return $user->can('flash_sales.show') || $user->can('flash_sales.view');
    }

    public function create(User $user): bool
    {
        return $user->can('flash_sales.create');
    }

    public function update(User $user, FlashSale $flashSale): bool
    {
        return $user->can('flash_sales.update');
    }

    public function delete(User $user, FlashSale $flashSale): bool
    {
        return $user->can('flash_sales.delete');
    }
}
