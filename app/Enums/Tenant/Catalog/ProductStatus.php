<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Lifecycle status for a catalog product.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
