<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\IndexCatalogDefinitionRequest;
use App\Http\Requests\Tenant\Catalog\StoreProductOptionRequest;
use App\Http\Requests\Tenant\Catalog\StoreProductOptionValueRequest;
use App\Http\Requests\Tenant\Catalog\UpdateProductOptionRequest;
use App\Http\Requests\Tenant\Catalog\UpdateProductOptionValueRequest;
use App\Http\Resources\Tenant\Catalog\ProductOptionResource;
use App\Models\Tenant\ProductOption;
use App\Models\Tenant\ProductOptionValue;
use App\Services\Tenant\Catalog\ProductOptionService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant product option endpoints.
 */
class ProductOptionController extends Controller
{
    public function __construct(private readonly ProductOptionService $optionService) {}

    /**
     * List options.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of options.',
        type: 'array{success: true, message: string, data: ProductOptionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexCatalogDefinitionRequest $request): JsonResponse
    {
        $options = $this->optionService->list($request->validated());

        return $this->success(
            ProductOptionResource::collection($options->items()),
            'Options retrieved successfully.',
            $this->paginationMeta($options),
        );
    }

    /**
     * Option select options.
     */
    #[Response(status: 200, description: 'Option options.', type: ApiResponseSchema::OPTIONS)]
    public function options(IndexCatalogDefinitionRequest $request): JsonResponse
    {
        return $this->success(
            $this->optionService->options($request->validated()),
            'Option options retrieved successfully.',
        );
    }

    /**
     * Create an option.
     */
    #[Response(
        status: 201,
        description: 'Created option.',
        type: 'array{success: true, message: string, data: ProductOptionResource, meta: null, errors: null}',
    )]
    public function store(StoreProductOptionRequest $request): JsonResponse
    {
        return $this->created(
            new ProductOptionResource($this->optionService->store($request->validated())),
            'Option created successfully.',
        );
    }

    /**
     * Show an option.
     */
    #[Response(
        status: 200,
        description: 'A single option.',
        type: 'array{success: true, message: string, data: ProductOptionResource, meta: null, errors: null}',
    )]
    public function show(ProductOption $option): JsonResponse
    {
        return $this->success(
            new ProductOptionResource($this->optionService->show($option)),
            'Option retrieved successfully.',
        );
    }

    /**
     * Update an option.
     */
    #[Response(
        status: 200,
        description: 'Updated option.',
        type: 'array{success: true, message: string, data: ProductOptionResource, meta: null, errors: null}',
    )]
    public function update(UpdateProductOptionRequest $request, ProductOption $option): JsonResponse
    {
        return $this->updated(
            new ProductOptionResource($this->optionService->update($option, $request->validated())),
            'Option updated successfully.',
        );
    }

    /**
     * Delete an option.
     */
    #[Response(
        status: 200,
        description: 'Option deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(ProductOption $option): JsonResponse
    {
        $this->optionService->destroy($option);

        return $this->deleted('Option deleted successfully.');
    }

    /**
     * Create an option value.
     */
    #[Response(
        status: 201,
        description: 'Created option value.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function storeValue(StoreProductOptionValueRequest $request, ProductOption $option): JsonResponse
    {
        $value = $this->optionService->storeValue($option, $request->validated());

        return $this->created($value, 'Option value created successfully.');
    }

    /**
     * Update an option value.
     */
    #[Response(
        status: 200,
        description: 'Updated option value.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function updateValue(
        UpdateProductOptionValueRequest $request,
        ProductOption $option,
        ProductOptionValue $value,
    ): JsonResponse {
        $value = $this->optionService->updateValue($option, $value, $request->validated());

        return $this->updated($value, 'Option value updated successfully.');
    }

    /**
     * Delete an option value.
     */
    #[Response(
        status: 200,
        description: 'Option value deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyValue(ProductOption $option, ProductOptionValue $value): JsonResponse
    {
        $this->optionService->destroyValue($option, $value);

        return $this->deleted('Option value deleted successfully.');
    }
}
