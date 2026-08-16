<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Delivery\DeliveryResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Services\Tenant\Delivery\DeliveryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Customer delivery visibility for their orders.
 */
class CustomerDeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveryService) {}

    #[Response(status: 200, description: 'Paginated deliveries for an order.', type: 'array{success: true, message: string, data: DeliveryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request, Order $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ($order->customer_id !== $customer->id) {
            throw new AccessDeniedHttpException('Order does not belong to this customer.');
        }

        $deliveries = $this->deliveryService->forCustomerOrder($order, $request->only(['per_page']));

        return $this->success(
            DeliveryResource::collection($deliveries->items()),
            'Deliveries retrieved successfully.',
            $this->paginationMeta($deliveries),
        );
    }
}
