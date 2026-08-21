<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\IndexProductBadgeRequest;
use App\Http\Requests\Tenant\Catalog\StoreProductBadgeRequest;
use App\Http\Requests\Tenant\Catalog\SyncProductBadgesRequest;
use App\Http\Requests\Tenant\Catalog\UpdateProductBadgeRequest;
use App\Http\Resources\Tenant\Catalog\ProductBadgeResource;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBadge;
use App\Services\Tenant\Catalog\ProductBadgeService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant product badge endpoints.
 */
class ProductBadgeController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  ProductBadgeService  $badgeService
     */
    public function __construct(private readonly ProductBadgeService $badgeService) {}

    /**
     * List badges.
     *
     * @param  IndexProductBadgeRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of badges.',
        type: 'array{success: true, message: string, data: ProductBadgeResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexProductBadgeRequest $request): JsonResponse
    {
        $badges = $this->badgeService->list($request->validated());

        return $this->success(
            ProductBadgeResource::collection($badges->items()),
            'Badges retrieved successfully.',
            $this->paginationMeta($badges),
        );
    }

    /**
     * Badge options for select inputs.
     *
     * @param  IndexProductBadgeRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Badge options.', type: ApiResponseSchema::OPTIONS)]
    public function options(IndexProductBadgeRequest $request): JsonResponse
    {
        return $this->success(
            $this->badgeService->options($request->validated()),
            'Badge options retrieved successfully.',
        );
    }

    /**
     * Create a badge.
     *
     * @param  StoreProductBadgeRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created badge.',
        type: 'array{success: true, message: string, data: ProductBadgeResource, meta: null, errors: null}',
    )]
    public function store(StoreProductBadgeRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['image']);
        $badge = $this->badgeService->store($data, $request->file('image'));

        return $this->created(
            new ProductBadgeResource($badge),
            'Badge created successfully.',
        );
    }

    /**
     * Show a badge.
     *
     * @param  ProductBadge  $badge
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single badge.',
        type: 'array{success: true, message: string, data: ProductBadgeResource, meta: null, errors: null}',
    )]
    public function show(ProductBadge $badge): JsonResponse
    {
        return $this->success(
            new ProductBadgeResource($this->badgeService->show($badge)),
            'Badge retrieved successfully.',
        );
    }

    /**
     * Update a badge.
     *
     * @param  UpdateProductBadgeRequest  $request
     * @param  ProductBadge  $badge
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated badge.',
        type: 'array{success: true, message: string, data: ProductBadgeResource, meta: null, errors: null}',
    )]
    public function update(UpdateProductBadgeRequest $request, ProductBadge $badge): JsonResponse
    {
        $data = $request->safe()->except(['image']);
        $badge = $this->badgeService->update($badge, $data, $request->file('image'));

        return $this->updated(
            new ProductBadgeResource($badge),
            'Badge updated successfully.',
        );
    }

    /**
     * Delete a badge.
     *
     * @param  ProductBadge  $badge
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Badge deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(ProductBadge $badge): JsonResponse
    {
        $this->badgeService->destroy($badge);

        return $this->deleted('Badge deleted successfully.');
    }

    /**
     * Sync badges on a product.
     *
     * @param  SyncProductBadgesRequest  $request
     * @param  Product  $product
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Product with synced badges.',
        type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}',
    )]
    public function syncToProduct(SyncProductBadgesRequest $request, Product $product): JsonResponse
    {
        $product = $this->badgeService->syncToProduct($product, $request->validated('badges'));

        return $this->updated(
            new ProductResource($product),
            'Product badges synced successfully.',
        );
    }
}
