<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Shipping;

use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shipping\StoreShipmentRequest;
use App\Http\Requests\Tenant\Shipping\TransitionShipmentRequest;
use App\Http\Resources\Tenant\Shipping\ShipmentResource;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shipment;
use App\Services\Tenant\Shipping\ShipmentService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin shipment endpoints.
 */
class ShipmentController extends Controller
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
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated shipments.', type: 'array{success: true, message: string, data: ShipmentResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        $shipments = $this->shipmentService->list($request->only(['order_id', 'status', 'per_page']));

        return $this->success(
            ShipmentResource::collection($shipments->items()),
            'Shipments retrieved successfully.',
            $this->paginationMeta($shipments),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreShipmentRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created shipment.', type: 'array{success: true, message: string, data: ShipmentResource, meta: null, errors: null}')]
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $this->authorize('create', Shipment::class);

        $data = $request->validated();
        $order = Order::query()->findOrFail($data['order_id']);
        unset($data['order_id']);

        return $this->created(
            new ShipmentResource($this->shipmentService->create($order, $data)),
            'Shipment created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Shipment  $shipment
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A shipment.', type: 'array{success: true, message: string, data: ShipmentResource, meta: null, errors: null}')]
    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        return $this->success(
            new ShipmentResource($this->shipmentService->show($shipment)),
            'Shipment retrieved successfully.',
        );
    }

    /**
     * Update status.
     *
     * @param  TransitionShipmentRequest  $request
     * @param  Shipment  $shipment
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated shipment status.', type: 'array{success: true, message: string, data: ShipmentResource, meta: null, errors: null}')]
    public function updateStatus(TransitionShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        $status = ShipmentStatus::from($request->validated('status'));

        return $this->updated(
            new ShipmentResource($this->shipmentService->transition($shipment, $status)),
            'Shipment status updated successfully.',
        );
    }
}
