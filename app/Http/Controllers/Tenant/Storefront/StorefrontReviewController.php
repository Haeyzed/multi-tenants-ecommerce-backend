<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Storefront\IndexStorefrontRequest;
use App\Http\Resources\Tenant\Catalog\ProductReviewResource;
use App\Models\Tenant\Product;
use App\Services\Tenant\Catalog\StorefrontCatalogService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public storefront approved review endpoints.
 */
class StorefrontReviewController extends Controller
{
    public function __construct(private readonly StorefrontCatalogService $catalogService) {}

    /**
     * List approved reviews for a product.
     */
    #[Response(
        status: 200,
        description: 'Paginated approved reviews.',
        type: 'array{success: true, message: string, data: ProductReviewResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexStorefrontRequest $request, Product $product): JsonResponse
    {
        $reviews = $this->catalogService->productReviews($product, $request->validated());

        return $this->success(
            ProductReviewResource::collection($reviews->items()),
            'Reviews retrieved successfully.',
            $this->paginationMeta($reviews),
        );
    }
}
