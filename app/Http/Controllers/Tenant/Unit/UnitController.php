<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Unit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Unit\IndexUnitRequest;
use App\Http\Requests\Tenant\Unit\StoreUnitRequest;
use App\Http\Requests\Tenant\Unit\UpdateUnitRequest;
use App\Http\Resources\Tenant\Unit\UnitResource;
use App\Models\Tenant\Unit;
use App\Services\Tenant\Unit\UnitService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant measurement unit catalog endpoints.
 */
class UnitController extends Controller
{
    public function __construct(private readonly UnitService $unitService) {}

    /**
     * List units with pagination, search, and filters.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of units.',
        type: 'array{success: true, message: string, data: UnitResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexUnitRequest $request): JsonResponse
    {
        $units = $this->unitService->list($request->validated());

        return $this->success(
            UnitResource::collection($units->items()),
            'Units retrieved successfully.',
            $this->paginationMeta($units),
        );
    }

    /**
     * Unit options for select inputs.
     */
    #[Response(
        status: 200,
        description: 'Unit options.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexUnitRequest $request): JsonResponse
    {
        return $this->success(
            $this->unitService->options($request->validated()),
            'Unit options retrieved successfully.',
        );
    }

    /**
     * Create a unit.
     */
    #[Response(
        status: 201,
        description: 'Created unit.',
        type: 'array{success: true, message: string, data: UnitResource, meta: null, errors: null}',
    )]
    public function store(StoreUnitRequest $request): JsonResponse
    {
        return $this->created(
            new UnitResource($this->unitService->store($request->validated())),
            'Unit created successfully.',
        );
    }

    /**
     * Show a unit.
     */
    #[Response(
        status: 200,
        description: 'A single unit.',
        type: 'array{success: true, message: string, data: UnitResource, meta: null, errors: null}',
    )]
    public function show(Unit $unit): JsonResponse
    {
        return $this->success(
            new UnitResource($this->unitService->show($unit)),
            'Unit retrieved successfully.',
        );
    }

    /**
     * Update a unit.
     */
    #[Response(
        status: 200,
        description: 'Updated unit.',
        type: 'array{success: true, message: string, data: UnitResource, meta: null, errors: null}',
    )]
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        return $this->updated(
            new UnitResource($this->unitService->update($unit, $request->validated())),
            'Unit updated successfully.',
        );
    }

    /**
     * Delete a unit.
     */
    #[Response(
        status: 200,
        description: 'Unit deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Unit $unit): JsonResponse
    {
        $this->unitService->destroy($unit);

        return $this->deleted('Unit deleted successfully.');
    }
}
