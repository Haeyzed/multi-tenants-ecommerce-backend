<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Delivery\AssignDeliveryRequest;
use App\Http\Requests\Tenant\Delivery\FailDeliveryRequest;
use App\Http\Requests\Tenant\Delivery\StoreDeliveryRequest;
use App\Http\Resources\Tenant\Delivery\DeliveryResource;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shipment;
use App\Services\Tenant\Delivery\DeliveryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff delivery management endpoints.
 */
class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveryService) {}

    #[Response(status: 200, description: 'Paginated deliveries.', type: 'array{success: true, message: string, data: DeliveryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Delivery::class);

        $deliveries = $this->deliveryService->list($request->only(['order_id', 'driver_id', 'status', 'per_page']));

        return $this->success(
            DeliveryResource::collection($deliveries->items()),
            'Deliveries retrieved successfully.',
            $this->paginationMeta($deliveries),
        );
    }

    #[Response(status: 201, description: 'Created delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function store(StoreDeliveryRequest $request): JsonResponse
    {
        $this->authorize('create', Delivery::class);

        $data = $request->validated();
        $order = Order::query()->findOrFail($data['order_id']);
        $shipment = isset($data['shipment_id'])
            ? Shipment::query()->findOrFail($data['shipment_id'])
            : null;

        return $this->created(
            new DeliveryResource($this->deliveryService->createForOrder($order, $shipment, $data)),
            'Delivery created successfully.',
        );
    }

    #[Response(status: 200, description: 'A delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function show(Delivery $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);

        return $this->success(
            new DeliveryResource($this->deliveryService->show($delivery)),
            'Delivery retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Assigned delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function assign(AssignDeliveryRequest $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('update', $delivery);

        $driver = Driver::query()->findOrFail($request->validated('driver_id'));

        return $this->updated(
            new DeliveryResource($this->deliveryService->assign($delivery, $driver)),
            'Delivery assigned successfully.',
        );
    }

    #[Response(status: 200, description: 'Cancelled delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function cancel(Delivery $delivery): JsonResponse
    {
        $this->authorize('update', $delivery);

        return $this->updated(
            new DeliveryResource($this->deliveryService->cancel($delivery)),
            'Delivery cancelled successfully.',
        );
    }

    #[Response(status: 200, description: 'Failed delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function fail(FailDeliveryRequest $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('update', $delivery);

        return $this->updated(
            new DeliveryResource($this->deliveryService->markFailed($delivery, $request->validated())),
            'Delivery marked as failed.',
        );
    }
}
