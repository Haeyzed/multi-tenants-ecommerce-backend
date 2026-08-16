<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Delivery\FailDeliveryRequest;
use App\Http\Resources\Tenant\Delivery\DeliveryResource;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Services\Tenant\Delivery\DeliveryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Driver-facing delivery endpoints for their own assignments.
 */
class DriverDeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveryService) {}

    #[Response(status: 200, description: 'Paginated deliveries for the driver.', type: 'array{success: true, message: string, data: DeliveryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authorize('viewAny', Delivery::class);

        $deliveries = $this->deliveryService->forDriver($driver, $request->only(['status', 'per_page']));

        return $this->success(
            DeliveryResource::collection($deliveries->items()),
            'Deliveries retrieved successfully.',
            $this->paginationMeta($deliveries),
        );
    }

    #[Response(status: 200, description: 'A delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function show(Delivery $delivery): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authorize('view', $delivery);

        return $this->success(
            new DeliveryResource($this->deliveryService->show($delivery)),
            'Delivery retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Accepted delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function accept(Delivery $delivery): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authorize('drive', $delivery);

        return $this->updated(
            new DeliveryResource($this->deliveryService->accept($delivery, $driver)),
            'Delivery accepted successfully.',
        );
    }

    #[Response(status: 200, description: 'Rejected delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function reject(Delivery $delivery): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authorize('drive', $delivery);

        return $this->updated(
            new DeliveryResource($this->deliveryService->reject($delivery, $driver)),
            'Delivery rejected successfully.',
        );
    }

    #[Response(status: 200, description: 'Picked up delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function markPickedUp(Delivery $delivery): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authorize('drive', $delivery);

        return $this->updated(
            new DeliveryResource($this->deliveryService->markPickedUp($delivery, $driver)),
            'Delivery marked as picked up.',
        );
    }

    #[Response(status: 200, description: 'Out for delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function markOutForDelivery(Delivery $delivery): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authorize('drive', $delivery);

        return $this->updated(
            new DeliveryResource($this->deliveryService->markOutForDelivery($delivery, $driver)),
            'Delivery marked as out for delivery.',
        );
    }

    #[Response(status: 200, description: 'Delivered delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function markDelivered(Delivery $delivery): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authorize('drive', $delivery);

        return $this->updated(
            new DeliveryResource($this->deliveryService->markDelivered($delivery, $driver)),
            'Delivery marked as delivered.',
        );
    }

    #[Response(status: 200, description: 'Failed delivery.', type: 'array{success: true, message: string, data: DeliveryResource, meta: null, errors: null}')]
    public function markFailed(FailDeliveryRequest $request, Delivery $delivery): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $this->authorize('drive', $delivery);

        return $this->updated(
            new DeliveryResource($this->deliveryService->markFailed($delivery, $request->validated(), $driver)),
            'Delivery marked as failed.',
        );
    }
}
