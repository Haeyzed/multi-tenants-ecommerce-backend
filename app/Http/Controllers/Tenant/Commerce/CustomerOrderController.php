<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\IndexCustomerOrderRequest;
use App\Http\Resources\Tenant\Commerce\OrderResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Services\Tenant\Commerce\OrderService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer order history endpoints.
 */
class CustomerOrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * List the authenticated customer's orders.
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
}
