<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\IndexProductCollectionRequest;
use App\Http\Requests\Tenant\Catalog\StoreProductCollectionRequest;
use App\Http\Requests\Tenant\Catalog\SyncCollectionProductsRequest;
use App\Http\Requests\Tenant\Catalog\UpdateProductCollectionRequest;
use App\Http\Resources\Tenant\Catalog\ProductCollectionResource;
use App\Models\Tenant\ProductCollection;
use App\Services\Tenant\Catalog\ProductCollectionService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant product collection endpoints.
 */
class CollectionController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  ProductCollectionService  $collectionService
     */
    public function __construct(private readonly ProductCollectionService $collectionService) {}

    /**
     * List collections with pagination, search, and filters.
     *
     * @param  IndexProductCollectionRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of collections.',
        type: 'array{success: true, message: string, data: ProductCollectionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexProductCollectionRequest $request): JsonResponse
    {
        $collections = $this->collectionService->list($request->validated());

        return $this->success(
            ProductCollectionResource::collection($collections->items()),
            'Collections retrieved successfully.',
            $this->paginationMeta($collections),
        );
    }

    /**
     * Create a collection.
     *
     * @param  StoreProductCollectionRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created collection.',
        type: 'array{success: true, message: string, data: ProductCollectionResource, meta: null, errors: null}',
    )]
    public function store(StoreProductCollectionRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['image']);
        $collection = $this->collectionService->store($data, $request->file('image'));

        return $this->created(
            new ProductCollectionResource($collection),
            'Collection created successfully.',
        );
    }

    /**
     * Show a collection.
     *
     * @param  ProductCollection  $collection
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single collection.',
        type: 'array{success: true, message: string, data: ProductCollectionResource, meta: null, errors: null}',
    )]
    public function show(ProductCollection $collection): JsonResponse
    {
        return $this->success(
            new ProductCollectionResource($this->collectionService->show($collection)),
            'Collection retrieved successfully.',
        );
    }

    /**
     * Update a collection.
     *
     * @param  UpdateProductCollectionRequest  $request
     * @param  ProductCollection  $collection
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated collection.',
        type: 'array{success: true, message: string, data: ProductCollectionResource, meta: null, errors: null}',
    )]
    public function update(UpdateProductCollectionRequest $request, ProductCollection $collection): JsonResponse
    {
        $data = $request->safe()->except(['image']);
        $collection = $this->collectionService->update($collection, $data, $request->file('image'));

        return $this->updated(
            new ProductCollectionResource($collection),
            'Collection updated successfully.',
        );
    }

    /**
     * Delete a collection.
     *
     * @param  ProductCollection  $collection
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Collection deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(ProductCollection $collection): JsonResponse
    {
        $this->collectionService->destroy($collection);

        return $this->deleted('Collection deleted successfully.');
    }

    /**
     * Sync products in a collection.
     *
     * @param  SyncCollectionProductsRequest  $request
     * @param  ProductCollection  $collection
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Collection with synced products.',
        type: 'array{success: true, message: string, data: ProductCollectionResource, meta: null, errors: null}',
    )]
    public function syncProducts(SyncCollectionProductsRequest $request, ProductCollection $collection): JsonResponse
    {
        $collection = $this->collectionService->syncProducts(
            $collection,
            $request->validated('products'),
        );

        return $this->updated(
            new ProductCollectionResource($collection),
            'Collection products synced successfully.',
        );
    }
}
