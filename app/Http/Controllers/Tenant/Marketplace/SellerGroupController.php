<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\IndexSellerGroupRequest;
use App\Http\Requests\Tenant\Marketplace\StoreSellerGroupRequest;
use App\Http\Requests\Tenant\Marketplace\UpdateSellerGroupRequest;
use App\Http\Resources\Tenant\Marketplace\SellerGroupResource;
use App\Models\Tenant\SellerGroup;
use App\Services\Tenant\Marketplace\SellerGroupService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant seller group classification endpoints.
 */
class SellerGroupController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  SellerGroupService  $sellerGroupService
     */
    public function __construct(private readonly SellerGroupService $sellerGroupService) {}

    /**
     * List seller groups with pagination, search, and filters.
     *
     * @param  IndexSellerGroupRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of seller groups.',
        type: 'array{success: true, message: string, data: SellerGroupResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexSellerGroupRequest $request): JsonResponse
    {
        $groups = $this->sellerGroupService->list($request->validated());

        return $this->success(
            SellerGroupResource::collection($groups->items()),
            'Seller groups retrieved successfully.',
            $this->paginationMeta($groups),
        );
    }

    /**
     * Seller group options for select inputs.
     *
     * @param  IndexSellerGroupRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Seller group options.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexSellerGroupRequest $request): JsonResponse
    {
        return $this->success(
            $this->sellerGroupService->options($request->validated()),
            'Seller group options retrieved successfully.',
        );
    }

    /**
     * Create a seller group.
     *
     * @param  StoreSellerGroupRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created seller group.',
        type: 'array{success: true, message: string, data: SellerGroupResource, meta: null, errors: null}',
    )]
    public function store(StoreSellerGroupRequest $request): JsonResponse
    {
        $group = $this->sellerGroupService->store($request->validated());

        return $this->created(
            new SellerGroupResource($group),
            'Seller group created successfully.',
        );
    }

    /**
     * Show a seller group.
     *
     * @param  SellerGroup  $sellerGroup
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single seller group.',
        type: 'array{success: true, message: string, data: SellerGroupResource, meta: null, errors: null}',
    )]
    public function show(SellerGroup $sellerGroup): JsonResponse
    {
        return $this->success(
            new SellerGroupResource($this->sellerGroupService->show($sellerGroup)),
            'Seller group retrieved successfully.',
        );
    }

    /**
     * Update a seller group.
     *
     * @param  UpdateSellerGroupRequest  $request
     * @param  SellerGroup  $sellerGroup
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated seller group.',
        type: 'array{success: true, message: string, data: SellerGroupResource, meta: null, errors: null}',
    )]
    public function update(UpdateSellerGroupRequest $request, SellerGroup $sellerGroup): JsonResponse
    {
        $group = $this->sellerGroupService->update($sellerGroup, $request->validated());

        return $this->updated(
            new SellerGroupResource($group),
            'Seller group updated successfully.',
        );
    }

    /**
     * Delete a seller group.
     *
     * @param  SellerGroup  $sellerGroup
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Seller group deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(SellerGroup $sellerGroup): JsonResponse
    {
        $this->sellerGroupService->destroy($sellerGroup);

        return $this->deleted('Seller group deleted successfully.');
    }
}
