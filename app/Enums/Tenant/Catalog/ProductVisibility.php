<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Storefront visibility for a catalog product.
 */
enum ProductVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
