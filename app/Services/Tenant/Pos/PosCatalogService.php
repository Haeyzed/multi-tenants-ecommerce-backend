<?php

declare(strict_types=1);

namespace App\Services\Tenant\Pos;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * POS product/variant lookup by search and barcode.
 */
class PosCatalogService
{
    /**
     * Search products and variants by name, SKU, or barcode.
     *
     * @param  array{q?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, ProductVariant>|LengthAwarePaginator<int, Product>
     */
    public function search(array $params = []): LengthAwarePaginator
    {
        $q = trim((string) ($params['q'] ?? ''));
        $perPage = max(1, min((int) ($params['per_page'] ?? 20), 100));

        $variants = ProductVariant::query()
            ->with(['product', 'prices' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)
            ->when($q !== '', function (Builder $query) use ($q): void {
                $like = '%'.$q.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhereHas('product', function (Builder $query) use ($like): void {
                            $query->where('name', 'like', $like)
                                ->orWhere('sku', 'like', $like);
                        });
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        if ($variants->total() > 0 || $q === '') {
            return $variants;
        }

        return Product::query()
            ->with(['prices' => fn ($query) => $query->where('is_active', true)])
            ->where('status', 'published')
            ->where(function (Builder $query) use ($q): void {
                $like = '%'.$q.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Exact barcode match on an active variant (falls back to product SKU).
     */
    public function findByBarcode(string $barcode): ProductVariant|Product
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode is required.',
            ]);
        }

        $variant = ProductVariant::query()
            ->with(['product', 'prices' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)
            ->where('barcode', $barcode)
            ->first();

        if ($variant !== null) {
            return $variant;
        }

        $variantBySku = ProductVariant::query()
            ->with(['product', 'prices' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)
            ->where('sku', $barcode)
            ->first();

        if ($variantBySku !== null) {
            return $variantBySku;
        }

        $product = Product::query()
            ->with(['prices' => fn ($query) => $query->where('is_active', true)])
            ->where('status', 'published')
            ->where(function (Builder $query) use ($barcode): void {
                $query->where('slug', $barcode)
                    ->orWhere('name', $barcode);
            })
            ->first();

        if ($product !== null) {
            return $product;
        }

        throw ValidationException::withMessages([
            'barcode' => 'No product found for this barcode.',
        ]);
    }
}
