<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Storefront\SearchStorefrontProductsRequest;
use App\Http\Requests\Tenant\Storefront\ShowStorefrontProductRequest;
use App\Http\Resources\Tenant\Storefront\StorefrontProductResource;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Catalog\ProductViewService;
use App\Services\Tenant\Catalog\StorefrontCatalogService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Public storefront product endpoints.
 */
class StorefrontProductController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  StorefrontCatalogService  $catalogService
     * @param  ProductViewService  $productViewService
     */
    public function __construct(
        private readonly StorefrontCatalogService $catalogService,
        private readonly ProductViewService $productViewService,
    ) {}

    /**
     * Search, filter, and sort storefront products.
     *
     * @param  SearchStorefrontProductsRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated storefront products.',
        type: 'array{success: true, message: string, data: StorefrontProductResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(SearchStorefrontProductsRequest $request): JsonResponse
    {
        $products = $this->catalogService->products($request->validated());

        return $this->success(
            StorefrontProductResource::collection($products->items()),
            'Products retrieved successfully.',
            $this->paginationMeta($products),
        );
    }

    /**
     * Show a storefront product by slug or id and record the view.
     *
     * @param  ShowStorefrontProductRequest  $request
     * @param  string  $product
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A storefront product.',
        type: 'array{success: true, message: string, data: StorefrontProductResource, meta: null, errors: null}',
    )]
    public function show(ShowStorefrontProductRequest $request, string $product): JsonResponse
    {
        $resolved = $this->catalogService->product($product);

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        $this->productViewService->record($resolved, $customer, $request->sessionKey());

        return $this->success(
            new StorefrontProductResource($resolved),
            'Product retrieved successfully.',
        );
    }
}
