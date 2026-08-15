<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Storefront\ProductRecommendationRequest;
use App\Http\Resources\Tenant\Storefront\StorefrontProductResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Services\Tenant\Catalog\ProductRecommendationService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Public storefront product recommendation endpoints.
 */
class StorefrontRecommendationController extends Controller
{
    public function __construct(private readonly ProductRecommendationService $recommendations) {}

    /**
     * Recommended products for a storefront product, grouped by recommendation type.
     */
    #[Response(
        status: 200,
        description: 'Recommended products grouped by type.',
        type: 'array{success: true, message: string, data: array<string, StorefrontProductResource[]>, meta: array{types: string[]}, errors: null}',
    )]
    public function index(ProductRecommendationRequest $request, Product $product): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        $results = $this->recommendations->recommend(
            types: $request->types(),
            product: $product,
            customer: $customer,
            limit: $request->limit(),
            sessionKey: $request->sessionKey(),
        );

        $data = [];

        foreach ($results as $type => $products) {
            /** @var Collection<int, Product> $products */
            $data[$type] = StorefrontProductResource::collection($products)->resolve();
        }

        return $this->success(
            $data,
            'Recommendations retrieved successfully.',
            ['types' => array_keys($data)],
        );
    }
}
