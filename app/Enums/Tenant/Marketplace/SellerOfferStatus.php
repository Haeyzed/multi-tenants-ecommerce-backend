<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Marketplace;

/**
 * Commercial offer availability status.
 */
enum SellerOfferStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OutOfStock = 'out_of_stock';
    case Suspended = 'suspended';
}
