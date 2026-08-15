<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Coupon;
use App\Models\Tenant\User;

/**
 * Authorization for discount coupons.
 */
class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('coupons.view');
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $user->can('coupons.show') || $user->can('coupons.view');
    }

    public function create(User $user): bool
    {
        return $user->can('coupons.create');
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->can('coupons.update');
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->can('coupons.delete');
    }
}
