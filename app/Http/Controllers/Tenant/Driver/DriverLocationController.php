<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Driver\StoreDriverLocationRequest;
use App\Http\Resources\Tenant\Driver\DriverLocationResource;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Driver;
use App\Services\Tenant\Driver\DriverLocationService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Driver GPS location updates.
 */
class DriverLocationController extends Controller
{
    public function __construct(private readonly DriverLocationService $locationService) {}

    /**
     * Record a location update for an active delivery.
     */
    #[Response(
        status: 200,
        description: 'Recorded location (or acknowledgement when throttled).',
        type: 'array{success: true, message: string, data: DriverLocationResource|null, meta: null, errors: null}',
    )]
    public function store(StoreDriverLocationRequest $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = Auth::guard('driver')->user();

        $data = $request->validated();
        $delivery = Delivery::query()->findOrFail($data['delivery_id']);

        $this->authorize('drive', $delivery);

        unset($data['delivery_id']);

        $location = $this->locationService->record($driver, $delivery, $data);

        return $this->success(
            $location !== null ? new DriverLocationResource($location) : null,
            $location !== null ? 'Location recorded successfully.' : 'Location acknowledged (throttled).',
        );
    }
}
