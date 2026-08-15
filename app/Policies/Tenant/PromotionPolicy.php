<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Promotion;
use App\Models\Tenant\User;

/**
 * Authorization for cart promotions.
 */
class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promotions.view');
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.show') || $user->can('promotions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('promotions.create');
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.update');
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.delete');
    }
}
