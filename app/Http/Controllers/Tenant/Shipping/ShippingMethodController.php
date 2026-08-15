<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shipping\StoreShippingMethodRequest;
use App\Http\Requests\Tenant\Shipping\UpdateShippingMethodRequest;
use App\Http\Resources\Tenant\Shipping\ShippingMethodResource;
use App\Models\Tenant\ShippingMethod;
use App\Services\Tenant\Shipping\ShippingMethodService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin shipping method CRUD.
 */
class ShippingMethodController extends Controller
{
    public function __construct(private readonly ShippingMethodService $shippingMethodService) {}

    #[Response(status: 200, description: 'Paginated shipping methods.', type: 'array{success: true, message: string, data: ShippingMethodResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShippingMethod::class);

        $methods = $this->shippingMethodService->list($request->only(['search', 'is_active', 'per_page']));

        return $this->success(
            ShippingMethodResource::collection($methods->items()),
            'Shipping methods retrieved successfully.',
            $this->paginationMeta($methods),
        );
    }

    #[Response(status: 200, description: 'Shipping method options.', type: ApiResponseSchema::OPTIONS)]
    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShippingMethod::class);

        return $this->success(
            $this->shippingMethodService->options($request->only(['is_active'])),
            'Shipping method options retrieved successfully.',
        );
    }

    #[Response(status: 201, description: 'Created shipping method.', type: 'array{success: true, message: string, data: ShippingMethodResource, meta: null, errors: null}')]
    public function store(StoreShippingMethodRequest $request): JsonResponse
    {
        $this->authorize('create', ShippingMethod::class);

        return $this->created(
            new ShippingMethodResource($this->shippingMethodService->store($request->validated())),
            'Shipping method created successfully.',
        );
    }

    #[Response(status: 200, description: 'A shipping method.', type: 'array{success: true, message: string, data: ShippingMethodResource, meta: null, errors: null}')]
    public function show(ShippingMethod $shippingMethod): JsonResponse
    {
        $this->authorize('view', $shippingMethod);

        return $this->success(
            new ShippingMethodResource($this->shippingMethodService->show($shippingMethod)),
            'Shipping method retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated shipping method.', type: 'array{success: true, message: string, data: ShippingMethodResource, meta: null, errors: null}')]
    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $this->authorize('update', $shippingMethod);

        return $this->updated(
            new ShippingMethodResource($this->shippingMethodService->update($shippingMethod, $request->validated())),
            'Shipping method updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted shipping method.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(ShippingMethod $shippingMethod): JsonResponse
    {
        $this->authorize('delete', $shippingMethod);
        $this->shippingMethodService->destroy($shippingMethod);

        return $this->deleted('Shipping method deleted successfully.');
    }
}
