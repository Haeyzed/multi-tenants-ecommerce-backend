<?php

declare(strict_types=1);

namespace App\Services\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductSpecification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Product specification row replace-set operations.
 */
class ProductSpecificationService
{
    /**
     * List specification rows for a product.
     *
     * @return Collection<int, ProductSpecification>
     */
    public function list(Product $product): Collection
    {
        return $product->specifications()->get();
    }

    /**
     * Replace-set specification rows for a product.
     *
     * @param  list<array{group?: string|null, name: string, value: string, sort_order?: int}>  $items
     * @return Collection<int, ProductSpecification>
     */
    public function sync(Product $product, array $items): Collection
    {
        return DB::transaction(function () use ($product, $items): Collection {
            $product->specifications()->delete();

            foreach ($items as $index => $item) {
                $product->specifications()->create([
                    'group' => $item['group'] ?? null,
                    'name' => $item['name'],
                    'value' => $item['value'],
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                ]);
            }

            return $this->list($product);
        });
    }
}
