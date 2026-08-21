<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\IndexCustomerOrderRequest;
use App\Http\Resources\Tenant\Commerce\OrderResource;
use App\Http\Resources\Tenant\Commerce\RefundResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Services\Tenant\Commerce\OrderService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Customer order history endpoints.
 */
class CustomerOrderController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  OrderService  $orderService
     */
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * List the authenticated customer's orders.
     *
     * @param  IndexCustomerOrderRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated customer orders.',
        type: 'array{success: true, message: string, data: OrderResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexCustomerOrderRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $orders = $this->orderService->customerList($customer, $request->validated());

        return $this->success(
            OrderResource::collection($orders->items()),
            'Orders retrieved successfully.',
            $this->paginationMeta($orders),
        );
    }

    /**
     * Show a single customer order.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single customer order.',
        type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}',
    )]
    public function show(Order $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            new OrderResource($this->orderService->customerShow($customer, $order)),
            'Order retrieved successfully.',
        );
    }

    /**
     * Cancel an unpaid-eligible customer order.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Cancelled customer order.',
        type: 'array{success: true, message: string, data: OrderResource, meta: null, errors: null}',
    )]
    public function cancel(Order $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            new OrderResource($this->orderService->customerCancel($customer, $order)),
            'Order cancelled successfully.',
        );
    }

    /**
     * List refunds for a customer-owned order.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated refunds for a customer order.',
        type: 'array{success: true, message: string, data: RefundResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function refunds(Request $request, Order $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $refunds = $this->orderService->customerRefunds($customer, $order, $request->only(['per_page']));

        return $this->success(
            RefundResource::collection($refunds->items()),
            'Order refunds retrieved successfully.',
            $this->paginationMeta($refunds),
        );
    }
}
