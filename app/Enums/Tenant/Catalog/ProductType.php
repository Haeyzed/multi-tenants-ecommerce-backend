<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Catalog product type (simple vs variable with variants).
 */
enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';
}
