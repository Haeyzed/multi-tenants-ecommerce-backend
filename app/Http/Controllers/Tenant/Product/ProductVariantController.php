<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\BaseRequest;
use App\Http\Requests\Tenant\Product\ProductVariant\IndexProductVariantRequest;
use App\Http\Requests\Tenant\Product\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\Tenant\Product\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\Tenant\Product\ProductVariantResource;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Product\ProductVariantService;
use App\Support\ApiResponseSchema;
use App\Support\Media\MediaValidation;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Tenant product variant endpoints nested under products.
 */
class ProductVariantController extends Controller
{
    public function __construct(private readonly ProductVariantService $variantService) {}

    /**
     * List variants for a product.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of product variants.',
        type: 'array{success: true, message: string, data: ProductVariantResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexProductVariantRequest $request, Product $product): JsonResponse
    {
        $variants = $this->variantService->listForProduct($product, $request->validated());

        return $this->success(
            ProductVariantResource::collection($variants->items()),
            'Product variants retrieved successfully.',
            $this->paginationMeta($variants),
        );
    }

    /**
     * Create a product variant.
     */
    #[Response(
        status: 201,
        description: 'Created product variant.',
        type: 'array{success: true, message: string, data: ProductVariantResource, meta: null, errors: null}',
    )]
    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $data = $request->safe()->except(['option_value_ids', 'price', 'image']);

        $variant = $this->variantService->store(
            $product,
            $data,
            $validated['option_value_ids'] ?? [],
            $validated['price'] ?? null,
            $request->file('image'),
        );

        return $this->created(
            new ProductVariantResource($variant),
            'Product variant created successfully.',
        );
    }

    /**
     * Show a product variant.
     */
    #[Response(
        status: 200,
        description: 'A single product variant.',
        type: 'array{success: true, message: string, data: ProductVariantResource, meta: null, errors: null}',
    )]
    public function show(Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        return $this->success(
            new ProductVariantResource($this->variantService->show($variant)),
            'Product variant retrieved successfully.',
        );
    }

    /**
     * Update a product variant.
     */
    #[Response(
        status: 200,
        description: 'Updated product variant.',
        type: 'array{success: true, message: string, data: ProductVariantResource, meta: null, errors: null}',
    )]
    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $validated = $request->validated();
        $data = $request->safe()->except(['option_value_ids', 'price', 'image']);

        $variant = $this->variantService->update(
            $variant,
            $data,
            array_key_exists('option_value_ids', $validated) ? $validated['option_value_ids'] : null,
            $validated['price'] ?? null,
            $request->file('image'),
        );

        return $this->updated(
            new ProductVariantResource($variant),
            'Product variant updated successfully.',
        );
    }

    /**
     * Delete a product variant.
     */
    #[Response(
        status: 200,
        description: 'Product variant deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $this->variantService->destroy($variant);

        return $this->deleted('Product variant deleted successfully.');
    }

    /**
     * Upload or replace a variant image.
     */
    #[Response(
        status: 200,
        description: 'Product variant with updated image.',
        type: 'array{success: true, message: string, data: ProductVariantResource, meta: null, errors: null}',
    )]
    public function storeImage(BaseRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $request->validate([
            'image' => MediaValidation::image(required: true),
        ]);

        /** @var UploadedFile $image */
        $image = $request->file('image');

        return $this->updated(
            new ProductVariantResource($this->variantService->storeImage($variant, $image)),
            'Product variant image updated successfully.',
        );
    }

    /**
     * Remove a variant image.
     */
    #[Response(
        status: 200,
        description: 'Product variant with image removed.',
        type: 'array{success: true, message: string, data: ProductVariantResource, meta: null, errors: null}',
    )]
    public function destroyImage(Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        return $this->updated(
            new ProductVariantResource($this->variantService->destroyImage($variant)),
            'Product variant image deleted successfully.',
        );
    }
}
