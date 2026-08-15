<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Tax;

/**
 * What a tax rule applies to.
 */
enum TaxAppliesTo: string
{
    case All = 'all';
    case Product = 'product';
    case Shipping = 'shipping';
}
