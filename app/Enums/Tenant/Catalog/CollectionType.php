<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * How a product collection membership is determined.
 *
 * Automatic membership rules can be added later.
 */
enum CollectionType: string
{
    case Manual = 'manual';
}
