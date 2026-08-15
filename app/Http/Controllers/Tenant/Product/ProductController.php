<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\IndexProductRequest;
use App\Http\Requests\Tenant\Product\StoreProductImagesRequest;
use App\Http\Requests\Tenant\Product\StoreProductRequest;
use App\Http\Requests\Tenant\Product\UpdateProductRequest;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Models\Tenant\Product;
use App\Services\Tenant\Product\ProductService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Tenant product catalog endpoints.
 */
class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    /**
     * List products with pagination, search, and filters.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of products.',
        type: 'array{success: true, message: string, data: ProductResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexProductRequest $request): JsonResponse
    {
        $products = $this->productService->list($request->validated());

        return $this->success(
            ProductResource::collection($products->items()),
            'Products retrieved successfully.',
            $this->paginationMeta($products),
        );
    }

    /**
     * Product options for select inputs.
     */
    #[Response(
        status: 200,
        description: 'Product options.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexProductRequest $request): JsonResponse
    {
        return $this->success(
            $this->productService->options($request->validated()),
            'Product options retrieved successfully.',
        );
    }

    /**
     * Create a product (supports multipart image uploads).
     */
    #[Response(
        status: 201,
        description: 'Created product.',
        type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}',
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $data = $request->safe()->except(['image', 'images', 'category_ids', 'price', 'sku']);
        $data['sku'] = $validated['sku'] ?? null;

        $product = $this->productService->store(
            $data,
            $request->file('image'),
            $request->file('images', []),
            $validated['category_ids'] ?? [],
            $validated['price'] ?? null,
        );

        return $this->created(
            new ProductResource($product),
            'Product created successfully.',
        );
    }

    /**
     * Show a product.
     */
    #[Response(
        status: 200,
        description: 'A single product.',
        type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}',
    )]
    public function show(Product $product): JsonResponse
    {
        return $this->success(
            new ProductResource($this->productService->show($product)),
            'Product retrieved successfully.',
        );
    }

    /**
     * Update a product (supports multipart image uploads).
     */
    #[Response(
        status: 200,
        description: 'Updated product.',
        type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}',
    )]
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $data = $request->safe()->except(['image', 'images', 'category_ids', 'attribute_value_ids', 'price']);

        $product = $this->productService->update(
            $product,
            $data,
            $request->file('image'),
            $request->file('images', []),
            $validated['category_ids'] ?? [],
            array_key_exists('attribute_value_ids', $validated) ? $validated['attribute_value_ids'] : null,
            $validated['price'] ?? null,
        );

        return $this->updated(
            new ProductResource($product),
            'Product updated successfully.',
        );
    }

    /**
     * Delete a product.
     */
    #[Response(
        status: 200,
        description: 'Product deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Product $product): JsonResponse
    {
        $this->productService->destroy($product);

        return $this->deleted('Product deleted successfully.');
    }

    /**
     * Upload product gallery images.
     */
    #[Response(
        status: 200,
        description: 'Product with updated gallery.',
        type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}',
    )]
    public function storeImages(StoreProductImagesRequest $request, Product $product): JsonResponse
    {
        /** @var UploadedFile|null $image */
        $image = $request->file('image');
        /** @var list<UploadedFile> $images */
        $images = $request->file('images', []);

        if ($image !== null) {
            $images[] = $image;
        }

        return $this->updated(
            new ProductResource($this->productService->storeImage($product, $images)),
            'Product images uploaded successfully.',
        );
    }

    /**
     * Remove product gallery images.
     */
    #[Response(
        status: 200,
        description: 'Product with images removed.',
        type: 'array{success: true, message: string, data: ProductResource, meta: null, errors: null}',
    )]
    public function destroyImages(StoreProductImagesRequest $request, Product $product): JsonResponse
    {
        $mediaIds = $request->validated('media_ids', []);

        return $this->updated(
            new ProductResource($this->productService->destroyImages($product, $mediaIds)),
            'Product images deleted successfully.',
        );
    }
}
