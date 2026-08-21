<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\StorePromotionRequest;
use App\Http\Requests\Tenant\Commerce\UpdatePromotionRequest;
use App\Http\Resources\Tenant\Commerce\PromotionResource;
use App\Models\Tenant\Promotion;
use App\Services\Tenant\Commerce\PromotionService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin promotion CRUD.
 */
class PromotionController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PromotionService  $promotionService
     */
    public function __construct(private readonly PromotionService $promotionService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated promotions.', type: 'array{success: true, message: string, data: PromotionResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Promotion::class);

        $promotions = $this->promotionService->list($request->only(['search', 'is_active', 'type', 'per_page']));

        return $this->success(
            PromotionResource::collection($promotions->items()),
            'Promotions retrieved successfully.',
            $this->paginationMeta($promotions),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StorePromotionRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created promotion.', type: 'array{success: true, message: string, data: PromotionResource, meta: null, errors: null}')]
    public function store(StorePromotionRequest $request): JsonResponse
    {
        $this->authorize('create', Promotion::class);

        return $this->created(
            new PromotionResource($this->promotionService->store($request->validated())),
            'Promotion created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Promotion  $promotion
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A promotion.', type: 'array{success: true, message: string, data: PromotionResource, meta: null, errors: null}')]
    public function show(Promotion $promotion): JsonResponse
    {
        $this->authorize('view', $promotion);

        return $this->success(
            new PromotionResource($this->promotionService->show($promotion)),
            'Promotion retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdatePromotionRequest  $request
     * @param  Promotion  $promotion
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated promotion.', type: 'array{success: true, message: string, data: PromotionResource, meta: null, errors: null}')]
    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $this->authorize('update', $promotion);

        return $this->updated(
            new PromotionResource($this->promotionService->update($promotion, $request->validated())),
            'Promotion updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  Promotion  $promotion
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted promotion.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->authorize('delete', $promotion);
        $this->promotionService->destroy($promotion);

        return $this->deleted('Promotion deleted successfully.');
    }
}
