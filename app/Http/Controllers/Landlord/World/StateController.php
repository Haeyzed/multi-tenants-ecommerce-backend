<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\World\IndexStateRequest;
use App\Http\Resources\Landlord\World\StateResource;
use App\Services\Landlord\World\StateService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord API controller for states.
 */
class StateController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly StateService $stateService) {}

    /**
     * List states with pagination, search, and filters.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of states.',
        type: 'array{success: true, message: string, data: StateResource[], meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}, errors: null}',
    )]
    public function index(IndexStateRequest $request): JsonResponse
    {
        $states = $this->stateService->list($request->validated());

        return $this->success(
            StateResource::collection($states->items()),
            'States retrieved successfully.',
            $this->paginationMeta($states),
        );
    }

    /**
     * Return state options as label/value pairs.
     */
    #[Response(
        status: 200,
        description: 'State options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexStateRequest $request): JsonResponse
    {
        return $this->success(
            $this->stateService->options($request->validated())->all(),
            'State options retrieved successfully.',
        );
    }

    /**
     * Show a single state by identifier.
     */
    #[Response(
        status: 200,
        description: 'A single state.',
        type: 'array{success: true, message: string, data: StateResource, meta: null, errors: null}',
    )]
    public function show(int $state): JsonResponse
    {
        return $this->success(
            new StateResource($this->stateService->show($state)),
            'State retrieved successfully.',
        );
    }
}
