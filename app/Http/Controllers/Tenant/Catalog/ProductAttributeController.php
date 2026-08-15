<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\IndexCatalogDefinitionRequest;
use App\Http\Requests\Tenant\Catalog\StoreProductAttributeRequest;
use App\Http\Requests\Tenant\Catalog\StoreProductAttributeValueRequest;
use App\Http\Requests\Tenant\Catalog\UpdateProductAttributeRequest;
use App\Http\Requests\Tenant\Catalog\UpdateProductAttributeValueRequest;
use App\Http\Resources\Tenant\Catalog\ProductAttributeResource;
use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\ProductAttributeValue;
use App\Services\Tenant\Catalog\ProductAttributeService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant product attribute endpoints.
 */
class ProductAttributeController extends Controller
{
    public function __construct(private readonly ProductAttributeService $attributeService) {}

    /**
     * List attributes.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of attributes.',
        type: 'array{success: true, message: string, data: ProductAttributeResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexCatalogDefinitionRequest $request): JsonResponse
    {
        $attributes = $this->attributeService->list($request->validated());

        return $this->success(
            ProductAttributeResource::collection($attributes->items()),
            'Attributes retrieved successfully.',
            $this->paginationMeta($attributes),
        );
    }

    /**
     * Attribute select options.
     */
    #[Response(status: 200, description: 'Attribute options.', type: ApiResponseSchema::OPTIONS)]
    public function options(IndexCatalogDefinitionRequest $request): JsonResponse
    {
        return $this->success(
            $this->attributeService->options($request->validated()),
            'Attribute options retrieved successfully.',
        );
    }

    /**
     * Create an attribute.
     */
    #[Response(
        status: 201,
        description: 'Created attribute.',
        type: 'array{success: true, message: string, data: ProductAttributeResource, meta: null, errors: null}',
    )]
    public function store(StoreProductAttributeRequest $request): JsonResponse
    {
        return $this->created(
            new ProductAttributeResource($this->attributeService->store($request->validated())),
            'Attribute created successfully.',
        );
    }

    /**
     * Show an attribute.
     */
    #[Response(
        status: 200,
        description: 'A single attribute.',
        type: 'array{success: true, message: string, data: ProductAttributeResource, meta: null, errors: null}',
    )]
    public function show(ProductAttribute $attribute): JsonResponse
    {
        return $this->success(
            new ProductAttributeResource($this->attributeService->show($attribute)),
            'Attribute retrieved successfully.',
        );
    }

    /**
     * Update an attribute.
     */
    #[Response(
        status: 200,
        description: 'Updated attribute.',
        type: 'array{success: true, message: string, data: ProductAttributeResource, meta: null, errors: null}',
    )]
    public function update(UpdateProductAttributeRequest $request, ProductAttribute $attribute): JsonResponse
    {
        return $this->updated(
            new ProductAttributeResource($this->attributeService->update($attribute, $request->validated())),
            'Attribute updated successfully.',
        );
    }

    /**
     * Delete an attribute.
     */
    #[Response(
        status: 200,
        description: 'Attribute deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(ProductAttribute $attribute): JsonResponse
    {
        $this->attributeService->destroy($attribute);

        return $this->deleted('Attribute deleted successfully.');
    }

    /**
     * Create an attribute value.
     */
    #[Response(
        status: 201,
        description: 'Created attribute value.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function storeValue(StoreProductAttributeValueRequest $request, ProductAttribute $attribute): JsonResponse
    {
        $value = $this->attributeService->storeValue($attribute, $request->validated());

        return $this->created($value, 'Attribute value created successfully.');
    }

    /**
     * Update an attribute value.
     */
    #[Response(
        status: 200,
        description: 'Updated attribute value.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function updateValue(
        UpdateProductAttributeValueRequest $request,
        ProductAttribute $attribute,
        ProductAttributeValue $value,
    ): JsonResponse {
        $value = $this->attributeService->updateValue($attribute, $value, $request->validated());

        return $this->updated($value, 'Attribute value updated successfully.');
    }

    /**
     * Delete an attribute value.
     */
    #[Response(
        status: 200,
        description: 'Attribute value deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyValue(ProductAttribute $attribute, ProductAttributeValue $value): JsonResponse
    {
        $this->attributeService->destroyValue($attribute, $value);

        return $this->deleted('Attribute value deleted successfully.');
    }
}
