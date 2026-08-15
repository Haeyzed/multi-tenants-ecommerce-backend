<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Relationship type between catalog products.
 */
enum ProductRelationType: string
{
    case Related = 'related';
    case Upsell = 'upsell';
    case CrossSell = 'cross_sell';
}
