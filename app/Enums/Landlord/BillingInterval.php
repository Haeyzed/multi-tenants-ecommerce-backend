<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

/**
 * Plan billing interval. Extensible beyond monthly/yearly.
 */
enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
