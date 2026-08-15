<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Lifecycle status for a product collection.
 */
enum CollectionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
}
