<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Shipping\ShipmentResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Services\Tenant\Shipping\ShipmentService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Customer shipment visibility for their orders.
 */
class CustomerShipmentController extends Controller
{
    public function __construct(private readonly ShipmentService $shipmentService) {}

    #[Response(status: 200, description: 'Shipments for an order.', type: 'array{success: true, message: string, data: ShipmentResource[], meta: null, errors: null}')]
    public function index(Order $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ($order->customer_id !== $customer->id) {
            throw new AccessDeniedHttpException('Order does not belong to this customer.');
        }

        return $this->success(
            ShipmentResource::collection($this->shipmentService->forCustomerOrder($order)),
            'Shipments retrieved successfully.',
        );
    }
}
