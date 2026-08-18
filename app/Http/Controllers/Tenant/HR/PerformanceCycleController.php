<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexPerformanceCycleRequest;
use App\Http\Requests\Tenant\HR\StorePerformanceCycleRequest;
use App\Http\Requests\Tenant\HR\UpdatePerformanceCycleRequest;
use App\Http\Resources\Tenant\HR\PerformanceCycleResource;
use App\Models\Tenant\PerformanceCycle;
use App\Services\Tenant\HR\PerformanceCycleService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Performance review cycles.
 */
#[Group('HR')]
class PerformanceCycleController extends Controller
{
    public function __construct(private readonly PerformanceCycleService $cycles) {}

    #[Response(status: 200, description: 'Paginated performance cycles.', type: 'array{success: true, message: string, data: PerformanceCycleResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexPerformanceCycleRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PerformanceCycle::class);

        $cycles = $this->cycles->list($request->validated());

        return $this->success(
            PerformanceCycleResource::collection($cycles->items()),
            'Performance cycles retrieved successfully.',
            $this->paginationMeta($cycles),
        );
    }

    #[Response(status: 201, description: 'Created performance cycle.', type: 'array{success: true, message: string, data: PerformanceCycleResource, meta: null, errors: null}')]
    public function store(StorePerformanceCycleRequest $request): JsonResponse
    {
        $this->authorize('create', PerformanceCycle::class);

        return $this->created(
            new PerformanceCycleResource($this->cycles->store($request->validated())),
            'Performance cycle created successfully.',
        );
    }

    #[Response(status: 200, description: 'A performance cycle.', type: 'array{success: true, message: string, data: PerformanceCycleResource, meta: null, errors: null}')]
    public function show(PerformanceCycle $performance_cycle): JsonResponse
    {
        $this->authorize('view', $performance_cycle);

        return $this->success(
            new PerformanceCycleResource($this->cycles->show($performance_cycle)),
            'Performance cycle retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated performance cycle.', type: 'array{success: true, message: string, data: PerformanceCycleResource, meta: null, errors: null}')]
    public function update(UpdatePerformanceCycleRequest $request, PerformanceCycle $performance_cycle): JsonResponse
    {
        $this->authorize('update', $performance_cycle);

        return $this->updated(
            new PerformanceCycleResource($this->cycles->update($performance_cycle, $request->validated())),
            'Performance cycle updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted performance cycle.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(PerformanceCycle $performance_cycle): JsonResponse
    {
        $this->authorize('delete', $performance_cycle);
        $this->cycles->destroy($performance_cycle);

        return $this->deleted('Performance cycle deleted successfully.');
    }
}
