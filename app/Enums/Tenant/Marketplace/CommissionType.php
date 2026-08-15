<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Marketplace;

/**
 * How marketplace commission is calculated for a seller.
 */
enum CommissionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case PercentagePlusFixed = 'percentage_plus_fixed';
}
