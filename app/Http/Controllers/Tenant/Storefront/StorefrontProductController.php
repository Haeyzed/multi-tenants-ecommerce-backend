<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Storefront\IndexStorefrontRequest;
use App\Http\Resources\Tenant\Storefront\StorefrontProductResource;
use App\Services\Tenant\Catalog\StorefrontCatalogService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public storefront product endpoints.
 */
class StorefrontProductController extends Controller
{
    public function __construct(private readonly StorefrontCatalogService $catalogService) {}

    /**
     * List storefront-visible products.
     */
    #[Response(
        status: 200,
        description: 'Paginated storefront products.',
        type: 'array{success: true, message: string, data: StorefrontProductResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexStorefrontRequest $request): JsonResponse
    {
        $products = $this->catalogService->products($request->validated());

        return $this->success(
            StorefrontProductResource::collection($products->items()),
            'Products retrieved successfully.',
            $this->paginationMeta($products),
        );
    }

    /**
     * Show a storefront product by slug or id.
     */
    #[Response(
        status: 200,
        description: 'A storefront product.',
        type: 'array{success: true, message: string, data: StorefrontProductResource, meta: null, errors: null}',
    )]
    public function show(string $product): JsonResponse
    {
        return $this->success(
            new StorefrontProductResource($this->catalogService->product($product)),
            'Product retrieved successfully.',
        );
    }
}
