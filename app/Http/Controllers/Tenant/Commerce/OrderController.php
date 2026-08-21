<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Enums\Tenant\Commerce\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\IndexOrderRequest;
use App\Http\Requests\Tenant\Commerce\TransitionOrderRequest;
use App\Http\Resources\Tenant\Commerce\OrderResource;
use App\Models\Tenant\Order;
use App\Services\Tenant\Commerce\OrderService;
use App\Services\Tenant\Commerce\OrderTransitionService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Admin tenant order management endpoints.
 */
class OrderController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  OrderService  $orderService
     * @param  OrderTransitionService  $transitions
     */
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderTransitionService $transitions,
    ) {}

    /**
     * List orders with filters.
     *
     * @param  IndexOrderRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated orders.',
        type: 'array{success: true, message: string, data: OrderResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexOrderRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $orders = $this->orderService->adminList($request->validated());

        return $this->success(
            OrderResource::collection($orders->items()),
            'Orders retrieved successfully.',
            $this->paginationMeta($orders),
        );
    }

    /**
     * Show an order.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single order.',
        type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}',
    )]
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return $this->success(
            new OrderResource($this->orderService->adminShow($order)),
            'Order retrieved successfully.',
        );
    }

    /**
     * Transition order status.
     *
     * @param  TransitionOrderRequest  $request
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated order status.',
        type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}',
    )]
    public function updateStatus(TransitionOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $status = OrderStatus::from($request->validated('status'));
        $order = $this->transitions->transition($order, $status);

        return $this->updated(
            new OrderResource($order),
            'Order status updated successfully.',
        );
    }

    /**
     * Cancel an order and release inventory reservations.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Cancelled order.',
        type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}',
    )]
    public function cancel(Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        return $this->updated(
            new OrderResource($this->orderService->cancel($order)),
            'Order cancelled successfully.',
        );
    }
}
