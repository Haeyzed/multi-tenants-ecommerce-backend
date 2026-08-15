<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Promotion discount calculation type.
 */
enum PromotionType: string
{
    case PercentageOffOrder = 'percentage_off_order';
    case FixedOffOrder = 'fixed_off_order';
    case PercentageOffCategory = 'percentage_off_category';
    case FreeShipping = 'free_shipping';
    case BuyXGetYSimple = 'buy_x_get_y_simple';
}
