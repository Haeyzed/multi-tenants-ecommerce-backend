<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\StoreCustomerProductReviewRequest;
use App\Http\Resources\Tenant\Catalog\ProductReviewResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Services\Tenant\Product\ProductReviewService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer product review endpoints.
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
     * Submit a pending product review.
     *
     * @param  StoreCustomerProductReviewRequest  $request
     * @param  Product  $product
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created review.',
        type: 'array{success: true, message: string, data: ProductReviewResource, meta: null, errors: null}',
    )]
    public function store(StoreCustomerProductReviewRequest $request, Product $product): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $review = $this->reviewService->customerStore($customer, $product, $request->validated());

        return $this->created(
            new ProductReviewResource($review),
            'Review submitted successfully.',
        );
    }
}
