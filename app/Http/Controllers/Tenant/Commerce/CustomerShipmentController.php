<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Shipping\ShipmentResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Services\Tenant\Shipping\ShipmentService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Customer shipment visibility for their orders.
 */
class CustomerShipmentController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  ShipmentService  $shipmentService
     */
    public function __construct(private readonly ShipmentService $shipmentService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated shipments for an order.', type: 'array{success: true, message: string, data: ShipmentResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request, Order $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ($order->customer_id !== $customer->id) {
            throw new AccessDeniedHttpException('Order does not belong to this customer.');
        }

        $shipments = $this->shipmentService->forCustomerOrder($order, $request->only(['per_page']));

        return $this->success(
            ShipmentResource::collection($shipments->items()),
            'Shipments retrieved successfully.',
            $this->paginationMeta($shipments),
        );
    }
}
