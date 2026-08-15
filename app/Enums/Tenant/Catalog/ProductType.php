<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Catalog product type (simple, variable with variants, or bundle).
 */
enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';
    case Bundle = 'bundle';
}
