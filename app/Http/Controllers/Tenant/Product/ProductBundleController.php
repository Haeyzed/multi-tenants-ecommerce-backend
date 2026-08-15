<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\SyncProductBundleItemsRequest;
use App\Http\Resources\Tenant\Catalog\ProductBundleItemResource;
use App\Models\Tenant\Product;
use App\Services\Tenant\Product\ProductBundleService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Bundle product item endpoints.
 */
class ProductBundleController extends Controller
{
    public function __construct(private readonly ProductBundleService $bundleService) {}

    /**
     * List bundle items.
     */
    #[Response(
        status: 200,
        description: 'Bundle items.',
        type: 'array{success: true, message: string, data: ProductBundleItemResource[], meta: null, errors: null}',
    )]
    public function index(Product $product): JsonResponse
    {
        return $this->success(
            ProductBundleItemResource::collection($this->bundleService->listItems($product)),
            'Bundle items retrieved successfully.',
        );
    }

    /**
     * Sync bundle items.
     */
    #[Response(
        status: 200,
        description: 'Synced bundle items.',
        type: 'array{success: true, message: string, data: ProductBundleItemResource[], meta: null, errors: null}',
    )]
    public function sync(SyncProductBundleItemsRequest $request, Product $product): JsonResponse
    {
        $items = $this->bundleService->syncItems($product, $request->validated('items'));

        return $this->updated(
            ProductBundleItemResource::collection($items),
            'Bundle items synced successfully.',
        );
    }
}
