<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Storefront\IndexStorefrontRequest;
use App\Http\Resources\Tenant\Catalog\ProductCollectionResource;
use App\Services\Tenant\Catalog\StorefrontCatalogService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public storefront collection endpoints.
 */
class StorefrontCollectionController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  StorefrontCatalogService  $catalogService
     */
    public function __construct(private readonly StorefrontCatalogService $catalogService) {}

    /**
     * List storefront-visible collections.
     *
     * @param  IndexStorefrontRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated storefront collections.',
        type: 'array{success: true, message: string, data: ProductCollectionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexStorefrontRequest $request): JsonResponse
    {
        $collections = $this->catalogService->collections($request->validated());

        return $this->success(
            ProductCollectionResource::collection($collections->items()),
            'Collections retrieved successfully.',
            $this->paginationMeta($collections),
        );
    }

    /**
     * Show a storefront collection by slug or id.
     *
     * @param  string  $collection
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A storefront collection.',
        type: 'array{success: true, message: string, data: ProductCollectionResource, meta: null, errors: null}',
    )]
    public function show(string $collection): JsonResponse
    {
        return $this->success(
            new ProductCollectionResource($this->catalogService->collection($collection)),
            'Collection retrieved successfully.',
        );
    }
}
