<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Brand\IndexBrandRequest;
use App\Http\Requests\Tenant\Brand\StoreBrandLogoRequest;
use App\Http\Requests\Tenant\Brand\StoreBrandRequest;
use App\Http\Requests\Tenant\Brand\UpdateBrandRequest;
use App\Http\Resources\Tenant\Brand\BrandResource;
use App\Models\Tenant\Brand;
use App\Services\Tenant\Brand\BrandService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Tenant brand catalog endpoints.
 */
class BrandController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  BrandService  $brandService
     */
    public function __construct(private readonly BrandService $brandService) {}

    /**
     * List brands with pagination, search, and filters.
     *
     * @param  IndexBrandRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of brands.',
        type: 'array{success: true, message: string, data: BrandResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexBrandRequest $request): JsonResponse
    {
        $brands = $this->brandService->list($request->validated());

        return $this->success(
            BrandResource::collection($brands->items()),
            'Brands retrieved successfully.',
            $this->paginationMeta($brands),
        );
    }

    /**
     * Brand options for select inputs.
     *
     * @param  IndexBrandRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Brand options.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexBrandRequest $request): JsonResponse
    {
        return $this->success(
            $this->brandService->options($request->validated()),
            'Brand options retrieved successfully.',
        );
    }

    /**
     * Create a brand (supports multipart logo upload).
     *
     * @param  StoreBrandRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created brand.',
        type: 'array{success: true, message: string, data: BrandResource, meta: null, errors: null}',
    )]
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['logo']);
        $brand = $this->brandService->store($data, $request->file('logo'));

        return $this->created(
            new BrandResource($brand),
            'Brand created successfully.',
        );
    }

    /**
     * Show a brand.
     *
     * @param  Brand  $brand
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single brand.',
        type: 'array{success: true, message: string, data: BrandResource, meta: null, errors: null}',
    )]
    public function show(Brand $brand): JsonResponse
    {
        return $this->success(
            new BrandResource($this->brandService->show($brand)),
            'Brand retrieved successfully.',
        );
    }

    /**
     * Update a brand (supports multipart logo upload).
     *
     * @param  UpdateBrandRequest  $request
     * @param  Brand  $brand
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated brand.',
        type: 'array{success: true, message: string, data: BrandResource, meta: null, errors: null}',
    )]
    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $data = $request->safe()->except(['logo']);
        $brand = $this->brandService->update($brand, $data, $request->file('logo'));

        return $this->updated(
            new BrandResource($brand),
            'Brand updated successfully.',
        );
    }

    /**
     * Delete a brand.
     *
     * @param  Brand  $brand
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Brand deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Brand $brand): JsonResponse
    {
        $this->brandService->destroy($brand);

        return $this->deleted('Brand deleted successfully.');
    }

    /**
     * Upload or replace a brand logo.
     *
     * @param  StoreBrandLogoRequest  $request
     * @param  Brand  $brand
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Brand with updated logo.',
        type: 'array{success: true, message: string, data: BrandResource, meta: null, errors: null}',
    )]
    public function storeLogo(StoreBrandLogoRequest $request, Brand $brand): JsonResponse
    {
        /** @var UploadedFile $logo */
        $logo = $request->file('logo');

        return $this->updated(
            new BrandResource($this->brandService->storeLogo($brand, $logo)),
            'Brand logo updated successfully.',
        );
    }

    /**
     * Remove a brand logo.
     *
     * @param  Brand  $brand
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Brand with logo removed.',
        type: 'array{success: true, message: string, data: BrandResource, meta: null, errors: null}',
    )]
    public function destroyLogo(Brand $brand): JsonResponse
    {
        return $this->updated(
            new BrandResource($this->brandService->destroyLogo($brand)),
            'Brand logo deleted successfully.',
        );
    }
}
