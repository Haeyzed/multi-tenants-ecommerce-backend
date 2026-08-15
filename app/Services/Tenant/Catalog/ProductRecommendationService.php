<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Contracts\Catalog\ProductRecommendationProvider;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use Illuminate\Support\Collection;

/**
 * Resolves recommendation providers and fans a request out to the requested types.
 */
class ProductRecommendationService
{
    /**
     * Providers keyed by their public type identifier.
     *
     * @var array<string, ProductRecommendationProvider>
     */
    protected array $providers = [];

    /**
     * @param  iterable<ProductRecommendationProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    /**
     * Registered recommendation types.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Resolve recommendations grouped by type.
     *
     * Unknown types are ignored; passing no types runs every registered provider.
     *
     * @param  list<string>  $types
     * @return array<string, Collection<int, Product>>
     */
    public function recommend(
        array $types = [],
        ?Product $product = null,
        ?Customer $customer = null,
        int $limit = 8,
        ?string $sessionKey = null,
    ): array {
        $requested = $types === [] ? $this->types() : $types;
        $results = [];

        foreach ($requested as $type) {
            $provider = $this->providers[$type] ?? null;

            if ($provider === null) {
                continue;
            }

            $results[$type] = $provider->recommend($product, $customer, $limit, $sessionKey);
        }

        return $results;
    }
}
