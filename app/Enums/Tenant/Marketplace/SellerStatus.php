<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Marketplace;

/**
 * Seller account operational status.
 */
enum SellerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
