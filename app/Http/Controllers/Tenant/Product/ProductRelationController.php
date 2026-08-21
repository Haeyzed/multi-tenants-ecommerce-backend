<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Product;

use App\Enums\Tenant\Catalog\ProductRelationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\SyncProductRelationsRequest;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Models\Tenant\Product;
use App\Services\Tenant\Product\ProductRelationService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Product related / upsell / cross-sell endpoints.
 */
class ProductRelationController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  ProductRelationService  $relationService
     */
    public function __construct(private readonly ProductRelationService $relationService) {}

    /**
     * List relations of a given type.
     *
     * @param  Product  $product
     * @param  string  $type
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Related products.',
        type: 'array{success: true, message: string, data: ProductResource[], meta: null, errors: null}',
    )]
    public function show(Product $product, string $type): JsonResponse
    {
        $relationType = $this->resolveType($type);

        return $this->success(
            ProductResource::collection($this->relationService->list($product, $relationType)),
            'Product relations retrieved successfully.',
        );
    }

    /**
     * Sync relations of a given type.
     *
     * @param  SyncProductRelationsRequest  $request
     * @param  Product  $product
     * @param  string  $type
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Synced related products.',
        type: 'array{success: true, message: string, data: ProductResource[], meta: null, errors: null}',
    )]
    public function sync(SyncProductRelationsRequest $request, Product $product, string $type): JsonResponse
    {
        $relationType = $this->resolveType($type);
        $items = $this->relationService->sync($product, $relationType, $request->validated('items'));

        return $this->updated(
            ProductResource::collection($items),
            'Product relations synced successfully.',
        );
    }

    /**
     * Resolve type.
     *
     * @param  string  $type
     * @return ProductRelationType
     */
    protected function resolveType(string $type): ProductRelationType
    {
        $relationType = ProductRelationType::tryFrom($type);

        if ($relationType === null) {
            throw new NotFoundHttpException('Invalid relation type.');
        }

        return $relationType;
    }
}
