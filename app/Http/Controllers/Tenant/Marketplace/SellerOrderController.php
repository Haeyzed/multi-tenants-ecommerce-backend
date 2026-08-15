<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\IndexSellerOrderRequest;
use App\Http\Requests\Tenant\Marketplace\TransitionSellerOrderRequest;
use App\Http\Resources\Tenant\Marketplace\SellerOrderResource;
use App\Models\Tenant\SellerOrder;
use App\Services\Tenant\Marketplace\SellerOrderService;
use App\Services\Tenant\Marketplace\SellerOrderTransitionService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Admin and seller-scoped seller order management.
 */
class SellerOrderController extends Controller
{
    public function __construct(
        private readonly SellerOrderService $sellerOrders,
        private readonly SellerOrderTransitionService $transitions,
    ) {}

    #[Response(status: 200, description: 'Paginated seller orders.', type: 'array{success: true, message: string, data: SellerOrderResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexSellerOrderRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SellerOrder::class);

        $orders = $this->sellerOrders->list($request->validated(), $request->user());

        return $this->success(
            SellerOrderResource::collection($orders->items()),
            'Seller orders retrieved successfully.',
            $this->paginationMeta($orders),
        );
    }

    #[Response(status: 200, description: 'A seller order.', type: 'array{success: true, message: string, data: SellerOrderResource, meta: null, errors: null}')]
    public function show(SellerOrder $sellerOrder): JsonResponse
    {
        $this->authorize('view', $sellerOrder);

        return $this->success(
            new SellerOrderResource($this->sellerOrders->show($sellerOrder)),
            'Seller order retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated seller order status.', type: 'array{success: true, message: string, data: SellerOrderResource, meta: null, errors: null}')]
    public function updateStatus(TransitionSellerOrderRequest $request, SellerOrder $sellerOrder): JsonResponse
    {
        $this->authorize('manage', $sellerOrder);

        $status = SellerOrderStatus::from($request->validated('status'));
        $updated = $this->transitions->transition($sellerOrder, $status);

        return $this->updated(
            new SellerOrderResource($updated),
            'Seller order status updated successfully.',
        );
    }
}
