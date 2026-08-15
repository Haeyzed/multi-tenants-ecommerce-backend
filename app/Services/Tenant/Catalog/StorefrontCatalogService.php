<?php

declare(strict_types=1);

namespace App\Services\Tenant\Catalog;

use App\Enums\Tenant\Catalog\ProductReviewStatus;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCollection;
use App\Models\Tenant\ProductReview;
use App\Services\Tenant\Category\CategoryService;
use App\Services\Tenant\Product\ProductAvailabilityService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Public storefront catalog reads (no auth).
 */
class StorefrontCatalogService
{
    public function __construct(
        private readonly ProductAvailabilityService $availabilityService,
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * Paginate storefront-visible products.
     *
     * @param  array{
     *     search?: string|null,
     *     brand_id?: int|null,
     *     category_id?: int|null,
     *     collection_id?: int|null,
     *     tag_id?: int|null,
     *     is_featured?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(array $params = []): LengthAwarePaginator
    {
        $paginator = Product::query()
            ->storefrontVisible()
            ->with(['brand', 'media', 'prices' => fn ($query) => $query->where('is_active', true), 'tags', 'badges.media'])
            ->filter($params)
            ->when(
                array_key_exists('category_id', $params) && $params['category_id'] !== null,
                fn ($query) => $query->whereHas(
                    'categories',
                    fn ($query) => $query->where('categories.id', (int) $params['category_id']),
                ),
            )
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));

        $paginator->getCollection()->transform(function (Product $product): Product {
            return $this->attachAvailability($product);
        });

        return $paginator;
    }

    /**
     * Resolve a storefront product by slug or id.
     */
    public function product(string|int $slugOrId): Product
    {
        $query = Product::query()
            ->storefrontVisible()
            ->with([
                'brand.media',
                'unit',
                'categories.media',
                'media',
                'prices' => fn ($query) => $query->where('is_active', true),
                'tags',
                'badges.media',
                'specifications',
                'seo',
                'relatedProducts.media',
                'upsells.media',
                'crossSells.media',
                'bundleItems.product.media',
                'bundleItems.variant',
                'variants' => fn ($query) => $query->where('is_active', true),
            ]);

        /** @var Product|null $product */
        $product = is_numeric($slugOrId)
            ? (clone $query)->whereKey((int) $slugOrId)->first()
            : (clone $query)->where('slug', $slugOrId)->first();

        if ($product === null) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slugOrId]);
        }

        return $this->attachAvailability($product);
    }

    /**
     * Paginate storefront-visible collections.
     *
     * @param  array{search?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, ProductCollection>
     */
    public function collections(array $params = []): LengthAwarePaginator
    {
        return ProductCollection::query()
            ->storefrontVisible()
            ->with('media')
            ->withCount(['products' => fn ($query) => $query->storefrontVisible()])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Resolve a storefront collection by slug or id.
     */
    public function collection(string|int $slugOrId): ProductCollection
    {
        $query = ProductCollection::query()
            ->storefrontVisible()
            ->with([
                'media',
                'seo',
                'products' => fn ($query) => $query->storefrontVisible()->with(['media', 'brand', 'prices' => fn ($q) => $q->where('is_active', true)]),
            ]);

        /** @var ProductCollection|null $collection */
        $collection = is_numeric($slugOrId)
            ? (clone $query)->whereKey((int) $slugOrId)->first()
            : (clone $query)->where('slug', $slugOrId)->first();

        if ($collection === null) {
            throw (new ModelNotFoundException)->setModel(ProductCollection::class, [$slugOrId]);
        }

        $collection->setRelation(
            'products',
            $collection->products->map(fn (Product $product): Product => $this->attachAvailability($product)),
        );

        return $collection;
    }

    /**
     * Active brands for storefront.
     *
     * @param  array{search?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Brand>
     */
    public function brands(array $params = []): LengthAwarePaginator
    {
        return Brand::query()
            ->with('media')
            ->where('is_active', true)
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Resolve an active brand by slug or id.
     */
    public function brand(string|int $slugOrId): Brand
    {
        $query = Brand::query()->with(['media', 'seo'])->where('is_active', true);

        /** @var Brand|null $brand */
        $brand = is_numeric($slugOrId)
            ? (clone $query)->whereKey((int) $slugOrId)->first()
            : (clone $query)->where('slug', $slugOrId)->first();

        if ($brand === null) {
            throw (new ModelNotFoundException)->setModel(Brand::class, [$slugOrId]);
        }

        return $brand;
    }

    /**
     * Active categories for storefront.
     *
     * @param  array{search?: string|null, parent_id?: int|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Category>
     */
    public function categories(array $params = []): LengthAwarePaginator
    {
        $params['is_active'] = true;

        return Category::query()
            ->with(['media', 'parent'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Active category tree for storefront.
     *
     * @return Collection<int, Category>
     */
    public function categoryTree(): Collection
    {
        return $this->categoryService->tree(['is_active' => true]);
    }

    /**
     * Resolve an active category by slug or id.
     */
    public function category(string|int $slugOrId): Category
    {
        $query = Category::query()->with(['media', 'parent', 'seo', 'children'])->where('is_active', true);

        /** @var Category|null $category */
        $category = is_numeric($slugOrId)
            ? (clone $query)->whereKey((int) $slugOrId)->first()
            : (clone $query)->where('slug', $slugOrId)->first();

        if ($category === null) {
            throw (new ModelNotFoundException)->setModel(Category::class, [$slugOrId]);
        }

        return $category;
    }

    /**
     * Approved reviews for a storefront-visible product.
     *
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, ProductReview>
     */
    public function productReviews(Product $product, array $params = []): LengthAwarePaginator
    {
        if (! $this->availabilityService->isProductSellable($product)) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$product->id]);
        }

        return ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ProductReviewStatus::Approved)
            ->with('customer')
            ->applySort($params['sort'] ?? '-approved_at')
            ->paginate($this->perPage($params));
    }

    /**
     * Attach computed availability string on the model for resources.
     */
    protected function attachAvailability(Product $product): Product
    {
        $product->setAttribute('availability', $this->availabilityService->forProduct($product)->value);

        return $product;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
