<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\IndexProductTagRequest;
use App\Http\Requests\Tenant\Catalog\StoreProductTagRequest;
use App\Http\Requests\Tenant\Catalog\SyncProductTagsRequest;
use App\Http\Requests\Tenant\Catalog\UpdateProductTagRequest;
use App\Http\Resources\Tenant\Catalog\ProductTagResource;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductTag;
use App\Services\Tenant\Catalog\ProductTagService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant product tag endpoints.
 */
class ProductTagController extends Controller
{
    public function __construct(private readonly ProductTagService $tagService) {}

    /**
     * List tags.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of tags.',
        type: 'array{success: true, message: string, data: ProductTagResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexProductTagRequest $request): JsonResponse
    {
        $tags = $this->tagService->list($request->validated());

        return $this->success(
            ProductTagResource::collection($tags->items()),
            'Tags retrieved successfully.',
            $this->paginationMeta($tags),
        );
    }

    /**
     * Tag options for select inputs.
     */
    #[Response(status: 200, description: 'Tag options.', type: ApiResponseSchema::OPTIONS)]
    public function options(IndexProductTagRequest $request): JsonResponse
    {
        return $this->success(
            $this->tagService->options($request->validated()),
            'Tag options retrieved successfully.',
        );
    }

    /**
     * Create a tag.
     */
    #[Response(
        status: 201,
        description: 'Created tag.',
        type: 'array{success: true, message: string, data: ProductTagResource, meta: null, errors: null}',
    )]
    public function store(StoreProductTagRequest $request): JsonResponse
    {
        return $this->created(
            new ProductTagResource($this->tagService->store($request->validated())),
            'Tag created successfully.',
        );
    }

    /**
     * Show a tag.
     */
    #[Response(
        status: 200,
        description: 'A single tag.',
        type: 'array{success: true, message: string, data: ProductTagResource, meta: null, errors: null}',
    )]
    public function show(ProductTag $tag): JsonResponse
    {
        return $this->success(
            new ProductTagResource($this->tagService->show($tag)),
            'Tag retrieved successfully.',
        );
    }

    /**
     * Update a tag.
     */
    #[Response(
        status: 200,
        description: 'Updated tag.',
        type: 'array{success: true, message: string, data: ProductTagResource, meta: null, errors: null}',
    )]
    public function update(UpdateProductTagRequest $request, ProductTag $tag): JsonResponse
    {
        return $this->updated(
            new ProductTagResource($this->tagService->update($tag, $request->validated())),
            'Tag updated successfully.',
        );
    }

    /**
     * Delete a tag.
     */
    #[Response(
        status: 200,
        description: 'Tag deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(ProductTag $tag): JsonResponse
    {
        $this->tagService->destroy($tag);

        return $this->deleted('Tag deleted successfully.');
    }

    /**
     * Sync tags on a product.
     */
    #[Response(
        status: 200,
        description: 'Product with synced tags.',
        type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}',
    )]
    public function syncToProduct(SyncProductTagsRequest $request, Product $product): JsonResponse
    {
        $product = $this->tagService->syncToProduct($product, $request->validated('tag_ids'));

        return $this->updated(
            new ProductResource($product),
            'Product tags synced successfully.',
        );
    }
}
