<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\IndexProductReviewRequest;
use App\Http\Requests\Tenant\Product\ModerateProductReviewRequest;
use App\Http\Resources\Tenant\Catalog\ProductReviewResource;
use App\Models\Tenant\ProductReview;
use App\Services\Tenant\Product\ProductReviewService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Admin product review moderation endpoints.
 */
class ProductReviewController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  ProductReviewService  $reviewService
     */
    public function __construct(private readonly ProductReviewService $reviewService) {}

    /**
     * List reviews.
     *
     * @param  IndexProductReviewRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated reviews.',
        type: 'array{success: true, message: string, data: ProductReviewResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexProductReviewRequest $request): JsonResponse
    {
        $reviews = $this->reviewService->adminList($request->validated());

        return $this->success(
            ProductReviewResource::collection($reviews->items()),
            'Reviews retrieved successfully.',
            $this->paginationMeta($reviews),
        );
    }

    /**
     * Moderate a review status.
     *
     * @param  ModerateProductReviewRequest  $request
     * @param  ProductReview  $review
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Moderated review.',
        type: 'array{success: true, message: string, data: ProductReviewResource, meta: null, errors: null}',
    )]
    public function moderate(ModerateProductReviewRequest $request, ProductReview $review): JsonResponse
    {
        $review = $this->reviewService->moderate($review, $request->validated('status'));

        return $this->updated(
            new ProductReviewResource($review),
            'Review moderated successfully.',
        );
    }

    /**
     * Delete a review.
     *
     * @param  ProductReview  $review
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Review deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(ProductReview $review): JsonResponse
    {
        $this->reviewService->destroy($review);

        return $this->deleted('Review deleted successfully.');
    }
}
