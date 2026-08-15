<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Computed or declared sellable availability for a product or variant.
 */
enum ProductAvailability: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
    case Preorder = 'preorder';
    case Backorder = 'backorder';
    case Unavailable = 'unavailable';
}
