<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\UpsertSeoRequest;
use App\Http\Resources\Tenant\Catalog\SeoMetaResource;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCollection;
use App\Services\Tenant\Catalog\SeoService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Thin SEO show/upsert endpoints for catalog models.
 */
class SeoController extends Controller
{
    public function __construct(private readonly SeoService $seoService) {}

    /**
     * Show SEO for a brand.
     */
    #[Response(status: 200, description: 'Brand SEO.', type: 'array{success: true, message: string, data: SeoMetaResource|null, meta: null, errors: null}')]
    public function showBrand(Brand $brand): JsonResponse
    {
        return $this->showFor($brand);
    }

    /**
     * Upsert SEO for a brand.
     */
    #[Response(status: 200, description: 'Updated brand SEO.', type: 'array{success: true, message: string, data: SeoMetaResource, meta: null, errors: null}')]
    public function upsertBrand(UpsertSeoRequest $request, Brand $brand): JsonResponse
    {
        return $this->upsertFor($brand, $request->validated());
    }

    /**
     * Show SEO for a category.
     */
    #[Response(status: 200, description: 'Category SEO.', type: 'array{success: true, message: string, data: SeoMetaResource|null, meta: null, errors: null}')]
    public function showCategory(Category $category): JsonResponse
    {
        return $this->showFor($category);
    }

    /**
     * Upsert SEO for a category.
     */
    #[Response(status: 200, description: 'Updated category SEO.', type: 'array{success: true, message: string, data: SeoMetaResource, meta: null, errors: null}')]
    public function upsertCategory(UpsertSeoRequest $request, Category $category): JsonResponse
    {
        return $this->upsertFor($category, $request->validated());
    }

    /**
     * Show SEO for a product.
     */
    #[Response(status: 200, description: 'Product SEO.', type: 'array{success: true, message: string, data: SeoMetaResource|null, meta: null, errors: null}')]
    public function showProduct(Product $product): JsonResponse
    {
        return $this->showFor($product);
    }

    /**
     * Upsert SEO for a product.
     */
    #[Response(status: 200, description: 'Updated product SEO.', type: 'array{success: true, message: string, data: SeoMetaResource, meta: null, errors: null}')]
    public function upsertProduct(UpsertSeoRequest $request, Product $product): JsonResponse
    {
        return $this->upsertFor($product, $request->validated());
    }

    /**
     * Show SEO for a collection.
     */
    #[Response(status: 200, description: 'Collection SEO.', type: 'array{success: true, message: string, data: SeoMetaResource|null, meta: null, errors: null}')]
    public function showCollection(ProductCollection $collection): JsonResponse
    {
        return $this->showFor($collection);
    }

    /**
     * Upsert SEO for a collection.
     */
    #[Response(status: 200, description: 'Updated collection SEO.', type: 'array{success: true, message: string, data: SeoMetaResource, meta: null, errors: null}')]
    public function upsertCollection(UpsertSeoRequest $request, ProductCollection $collection): JsonResponse
    {
        return $this->upsertFor($collection, $request->validated());
    }

    /**
     * @param  Model&object{seo(): mixed}  $model
     */
    protected function showFor(Model $model): JsonResponse
    {
        $seo = $this->seoService->show($model);

        return $this->success(
            $seo ? new SeoMetaResource($seo) : null,
            'SEO retrieved successfully.',
        );
    }

    /**
     * @param  Model&object{seo(): mixed}  $model
     * @param  array<string, mixed>  $data
     */
    protected function upsertFor(Model $model, array $data): JsonResponse
    {
        return $this->updated(
            new SeoMetaResource($this->seoService->upsert($model, $data)),
            'SEO updated successfully.',
        );
    }
}
