<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Warehouse\IndexWarehouseRequest;
use App\Http\Requests\Tenant\Warehouse\StoreWarehouseLocationRequest;
use App\Http\Requests\Tenant\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Tenant\Warehouse\UpdateWarehouseLocationRequest;
use App\Http\Requests\Tenant\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\Tenant\Warehouse\WarehouseLocationResource;
use App\Http\Resources\Tenant\Warehouse\WarehouseResource;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseLocation;
use App\Services\Tenant\Warehouse\WarehouseService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant warehouse catalog endpoints.
 */
class WarehouseController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  WarehouseService  $warehouseService
     */
    public function __construct(private readonly WarehouseService $warehouseService) {}

    /**
     * List warehouses with pagination, search, and filters.
     *
     * @param  IndexWarehouseRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of warehouses.',
        type: 'array{success: true, message: string, data: WarehouseResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexWarehouseRequest $request): JsonResponse
    {
        $warehouses = $this->warehouseService->list($request->validated());

        return $this->success(
            WarehouseResource::collection($warehouses->items()),
            'Warehouses retrieved successfully.',
            $this->paginationMeta($warehouses),
        );
    }

    /**
     * Warehouse options for select inputs.
     *
     * @param  IndexWarehouseRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Warehouse options.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexWarehouseRequest $request): JsonResponse
    {
        return $this->success(
            $this->warehouseService->options($request->validated()),
            'Warehouse options retrieved successfully.',
        );
    }

    /**
     * Create a warehouse.
     *
     * @param  StoreWarehouseRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created warehouse.',
        type: 'array{success: true, message: string, data: WarehouseResource, meta: null, errors: null}',
    )]
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        return $this->created(
            new WarehouseResource($this->warehouseService->store($request->validated())),
            'Warehouse created successfully.',
        );
    }

    /**
     * Show a warehouse.
     *
     * @param  Warehouse  $warehouse
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single warehouse.',
        type: 'array{success: true, message: string, data: WarehouseResource, meta: null, errors: null}',
    )]
    public function show(Warehouse $warehouse): JsonResponse
    {
        return $this->success(
            new WarehouseResource($this->warehouseService->show($warehouse)),
            'Warehouse retrieved successfully.',
        );
    }

    /**
     * Update a warehouse.
     *
     * @param  UpdateWarehouseRequest  $request
     * @param  Warehouse  $warehouse
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated warehouse.',
        type: 'array{success: true, message: string, data: WarehouseResource, meta: null, errors: null}',
    )]
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        return $this->updated(
            new WarehouseResource($this->warehouseService->update($warehouse, $request->validated())),
            'Warehouse updated successfully.',
        );
    }

    /**
     * Delete a warehouse.
     *
     * @param  Warehouse  $warehouse
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Warehouse deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->warehouseService->destroy($warehouse);

        return $this->deleted('Warehouse deleted successfully.');
    }

    /**
     * List locations for a warehouse.
     *
     * @param  IndexWarehouseRequest  $request
     * @param  Warehouse  $warehouse
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of warehouse locations.',
        type: 'array{success: true, message: string, data: WarehouseLocationResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function indexLocations(IndexWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $locations = $this->warehouseService->listLocations($warehouse, $request->validated());

        return $this->success(
            WarehouseLocationResource::collection($locations->items()),
            'Warehouse locations retrieved successfully.',
            $this->paginationMeta($locations),
        );
    }

    /**
     * Create a warehouse location.
     *
     * @param  StoreWarehouseLocationRequest  $request
     * @param  Warehouse  $warehouse
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created warehouse location.',
        type: 'array{success: true, message: string, data: WarehouseLocationResource, meta: null, errors: null}',
    )]
    public function storeLocation(StoreWarehouseLocationRequest $request, Warehouse $warehouse): JsonResponse
    {
        return $this->created(
            new WarehouseLocationResource($this->warehouseService->storeLocation($warehouse, $request->validated())),
            'Warehouse location created successfully.',
        );
    }

    /**
     * Update a warehouse location.
     *
     * @param  UpdateWarehouseLocationRequest  $request
     * @param  Warehouse  $warehouse
     * @param  WarehouseLocation  $location
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated warehouse location.',
        type: 'array{success: true, message: string, data: WarehouseLocationResource, meta: null, errors: null}',
    )]
    public function updateLocation(
        UpdateWarehouseLocationRequest $request,
        Warehouse $warehouse,
        WarehouseLocation $location,
    ): JsonResponse {
        abort_unless($location->warehouse_id === $warehouse->id, 404);

        return $this->updated(
            new WarehouseLocationResource($this->warehouseService->updateLocation($location, $request->validated())),
            'Warehouse location updated successfully.',
        );
    }

    /**
     * Delete a warehouse location.
     *
     * @param  Warehouse  $warehouse
     * @param  WarehouseLocation  $location
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Warehouse location deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroyLocation(Warehouse $warehouse, WarehouseLocation $location): JsonResponse
    {
        abort_unless($location->warehouse_id === $warehouse->id, 404);

        $this->warehouseService->destroyLocation($location);

        return $this->deleted('Warehouse location deleted successfully.');
    }
}
