<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\SyncProductSpecificationsRequest;
use App\Http\Resources\Tenant\Catalog\ProductSpecificationResource;
use App\Models\Tenant\Product;
use App\Services\Tenant\Product\ProductSpecificationService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Product specification sync endpoints.
 */
class ProductSpecificationController extends Controller
{
    public function __construct(private readonly ProductSpecificationService $specificationService) {}

    /**
     * List product specifications.
     */
    #[Response(
        status: 200,
        description: 'Product specifications.',
        type: 'array{success: true, message: string, data: ProductSpecificationResource[], meta: null, errors: null}',
    )]
    public function index(Product $product): JsonResponse
    {
        return $this->success(
            ProductSpecificationResource::collection($this->specificationService->list($product)),
            'Product specifications retrieved successfully.',
        );
    }

    /**
     * Sync product specifications.
     */
    #[Response(
        status: 200,
        description: 'Synced product specifications.',
        type: 'array{success: true, message: string, data: ProductSpecificationResource[], meta: null, errors: null}',
    )]
    public function sync(SyncProductSpecificationsRequest $request, Product $product): JsonResponse
    {
        $specs = $this->specificationService->sync($product, $request->validated('specifications'));

        return $this->updated(
            ProductSpecificationResource::collection($specs),
            'Product specifications synced successfully.',
        );
    }
}
