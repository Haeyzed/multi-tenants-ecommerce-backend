<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Sort modes supported by the product search abstraction.
 */
enum ProductSearchSort: string
{
    case Relevance = 'relevance';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case Newest = 'newest';
    case Oldest = 'oldest';
    case Rating = 'rating';
    case Popularity = 'popularity';
}
