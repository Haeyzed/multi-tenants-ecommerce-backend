<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Driver\IndexDriverRequest;
use App\Http\Requests\Tenant\Driver\StoreDriverRequest;
use App\Http\Requests\Tenant\Driver\UpdateDriverRequest;
use App\Http\Resources\Tenant\Driver\DriverResource;
use App\Models\Tenant\Driver;
use App\Services\Tenant\Driver\DriverService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Staff driver management endpoints.
 */
class DriverController extends Controller
{
    public function __construct(private readonly DriverService $driverService) {}

    /**
     * List drivers with pagination, search, and filters.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of drivers.',
        type: 'array{success: true, message: string, data: DriverResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexDriverRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        $drivers = $this->driverService->list($request->validated());

        return $this->success(
            DriverResource::collection($drivers->items()),
            'Drivers retrieved successfully.',
            $this->paginationMeta($drivers),
        );
    }

    /**
     * Create a driver.
     */
    #[Response(
        status: 201,
        description: 'Created driver.',
        type: 'array{success: true, message: string, data: DriverResource, meta: null, errors: null}',
    )]
    public function store(StoreDriverRequest $request): JsonResponse
    {
        $this->authorize('create', Driver::class);

        $driver = $this->driverService->store($request->validated());

        return $this->created(
            new DriverResource($driver),
            'Driver created successfully.',
        );
    }

    /**
     * Show a driver.
     */
    #[Response(
        status: 200,
        description: 'A single driver.',
        type: 'array{success: true, message: string, data: DriverResource, meta: null, errors: null}',
    )]
    public function show(Driver $driver): JsonResponse
    {
        $this->authorize('view', $driver);

        return $this->success(
            new DriverResource($this->driverService->show($driver)),
            'Driver retrieved successfully.',
        );
    }

    /**
     * Update a driver.
     */
    #[Response(
        status: 200,
        description: 'Updated driver.',
        type: 'array{success: true, message: string, data: DriverResource, meta: null, errors: null}',
    )]
    public function update(UpdateDriverRequest $request, Driver $driver): JsonResponse
    {
        $this->authorize('update', $driver);

        $driver = $this->driverService->update($driver, $request->validated());

        return $this->updated(
            new DriverResource($driver),
            'Driver updated successfully.',
        );
    }

    /**
     * Soft-delete a driver.
     */
    #[Response(
        status: 200,
        description: 'Driver deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Driver $driver): JsonResponse
    {
        $this->authorize('delete', $driver);

        $this->driverService->destroy($driver);

        return $this->deleted('Driver deleted successfully.');
    }
}
