<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Storefront\IndexStorefrontRequest;
use App\Http\Resources\Tenant\Brand\BrandResource;
use App\Services\Tenant\Catalog\StorefrontCatalogService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public storefront brand endpoints.
 */
class StorefrontBrandController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  StorefrontCatalogService  $catalogService
     */
    public function __construct(private readonly StorefrontCatalogService $catalogService) {}

    /**
     * List active brands.
     *
     * @param  IndexStorefrontRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated storefront brands.',
        type: 'array{success: true, message: string, data: BrandResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexStorefrontRequest $request): JsonResponse
    {
        $brands = $this->catalogService->brands($request->validated());

        return $this->success(
            BrandResource::collection($brands->items()),
            'Brands retrieved successfully.',
            $this->paginationMeta($brands),
        );
    }

    /**
     * Show an active brand by slug or id.
     *
     * @param  string  $brand
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A storefront brand.',
        type: 'array{success: true, message: string, data: BrandResource, meta: null, errors: null}',
    )]
    public function show(string $brand): JsonResponse
    {
        return $this->success(
            new BrandResource($this->catalogService->brand($brand)),
            'Brand retrieved successfully.',
        );
    }
}
