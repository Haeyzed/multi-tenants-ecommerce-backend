<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Storefront\IndexStorefrontRequest;
use App\Http\Resources\Tenant\Category\CategoryResource;
use App\Services\Tenant\Catalog\StorefrontCatalogService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public storefront category endpoints.
 */
class StorefrontCategoryController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  StorefrontCatalogService  $catalogService
     */
    public function __construct(private readonly StorefrontCatalogService $catalogService) {}

    /**
     * List active categories.
     *
     * @param  IndexStorefrontRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated storefront categories.',
        type: 'array{success: true, message: string, data: CategoryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexStorefrontRequest $request): JsonResponse
    {
        $categories = $this->catalogService->categories($request->validated());

        return $this->success(
            CategoryResource::collection($categories->items()),
            'Categories retrieved successfully.',
            $this->paginationMeta($categories),
        );
    }

    /**
     * Active category tree.
     *
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Storefront category tree.',
        type: 'array{success: true, message: string, data: CategoryResource[], meta: null, errors: null}',
    )]
    public function tree(): JsonResponse
    {
        return $this->success(
            CategoryResource::collection($this->catalogService->categoryTree()),
            'Category tree retrieved successfully.',
        );
    }

    /**
     * Show an active category by slug or id.
     *
     * @param  string  $category
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A storefront category.',
        type: 'array{success: true, message: string, data: CategoryResource, meta: null, errors: null}',
    )]
    public function show(string $category): JsonResponse
    {
        return $this->success(
            new CategoryResource($this->catalogService->category($category)),
            'Category retrieved successfully.',
        );
    }
}
