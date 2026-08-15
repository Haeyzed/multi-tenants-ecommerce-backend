<?php

declare(strict_types=1);

namespace App\Contracts\Catalog;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use Illuminate\Support\Collection;

/**
 * Strategy that produces product recommendations for a given context.
 */
interface ProductRecommendationProvider
{
    /**
     * Public identifier used in the `types` request parameter.
     */
    public function key(): string;

    /**
     * Resolve recommended products.
     *
     * @return Collection<int, Product>
     */
    public function recommend(
        ?Product $product = null,
        ?Customer $customer = null,
        int $limit = 8,
        ?string $sessionKey = null,
    ): Collection;
}
