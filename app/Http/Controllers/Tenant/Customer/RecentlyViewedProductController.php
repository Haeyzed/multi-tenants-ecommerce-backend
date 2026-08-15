<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Storefront\IndexStorefrontRequest;
use App\Http\Resources\Tenant\Storefront\StorefrontProductResource;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Catalog\ProductViewService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Recently viewed products for the authenticated customer.
 */
class RecentlyViewedProductController extends Controller
{
    public function __construct(private readonly ProductViewService $productViewService) {}

    /**
     * List the customer's recently viewed products, most recent first.
     */
    #[Response(
        status: 200,
        description: 'Paginated recently viewed products.',
        type: 'array{success: true, message: string, data: StorefrontProductResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexStorefrontRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $products = $this->productViewService->paginateRecentlyViewed(
            customer: $customer,
            perPage: (int) ($request->validated('per_page') ?? 15),
        );

        return $this->success(
            StorefrontProductResource::collection($products->items()),
            'Recently viewed products retrieved successfully.',
            $this->paginationMeta($products),
        );
    }
}
