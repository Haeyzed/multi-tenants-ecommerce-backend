<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Plan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Plan\IndexPlanRequest;
use App\Http\Requests\Landlord\Plan\StorePlanRequest;
use App\Http\Requests\Landlord\Plan\SyncPlanFeaturesRequest;
use App\Http\Requests\Landlord\Plan\UpdatePlanRequest;
use App\Http\Resources\Landlord\Plan\PlanResource;
use App\Models\Landlord\Plan;
use App\Services\Landlord\Plan\PlanService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord plan management endpoints.
 */
class PlanController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly PlanService $planService) {}

    /**
     * List plans.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of plans.',
        type: 'array{success: true, message: string, data: PlanResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexPlanRequest $request): JsonResponse
    {
        $plans = $this->planService->list($request->validated());

        return $this->success(
            PlanResource::collection($plans->items()),
            'Plans retrieved successfully.',
            $this->paginationMeta($plans),
        );
    }

    /**
     * Return plan options as label/value pairs.
     */
    #[Response(
        status: 200,
        description: 'Plan options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexPlanRequest $request): JsonResponse
    {
        return $this->success(
            $this->planService->options($request->validated()),
            'Plan options retrieved successfully.',
        );
    }

    /**
     * Create a plan.
     */
    #[Response(
        status: 201,
        description: 'Created plan.',
        type: 'array{success: true, message: string, data: PlanResource, meta: null, errors: null}',
    )]
    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->planService->store($request->validated());

        return $this->created(
            new PlanResource($plan),
            'Plan created successfully.',
        );
    }

    /**
     * Show a plan.
     */
    #[Response(
        status: 200,
        description: 'A single plan.',
        type: 'array{success: true, message: string, data: PlanResource, meta: null, errors: null}',
    )]
    public function show(Plan $plan): JsonResponse
    {
        return $this->success(
            new PlanResource($this->planService->show($plan)),
            'Plan retrieved successfully.',
        );
    }

    /**
     * Update a plan.
     */
    #[Response(
        status: 200,
        description: 'Updated plan.',
        type: 'array{success: true, message: string, data: PlanResource, meta: null, errors: null}',
    )]
    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->planService->update($plan, $request->validated());

        return $this->updated(
            new PlanResource($plan),
            'Plan updated successfully.',
        );
    }

    /**
     * Delete a plan.
     */
    #[Response(
        status: 200,
        description: 'Deleted plan confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Plan $plan): JsonResponse
    {
        $this->planService->destroy($plan);

        return $this->deleted('Plan deleted successfully.');
    }

    /**
     * Sync features onto a plan by slug.
     */
    #[Response(
        status: 200,
        description: 'Plan with synced features.',
        type: 'array{success: true, message: string, data: PlanResource, meta: null, errors: null}',
    )]
    public function syncFeatures(SyncPlanFeaturesRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->planService->syncFeatures($plan, $request->validated('features'));

        return $this->updated(
            new PlanResource($plan),
            'Plan features synced successfully.',
        );
    }
}
