<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Coupon discount calculation type.
 */
enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
