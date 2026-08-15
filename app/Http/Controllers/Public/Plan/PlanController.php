<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Plan;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\Plan\PlanResource;
use App\Models\Landlord\Plan;
use App\Services\Landlord\Plan\PlanService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public pricing-page plan endpoints.
 */
class PlanController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly PlanService $planService) {}

    /**
     * List publicly available plans with feature limits.
     */
    #[Response(
        status: 200,
        description: 'Public plans for the pricing page.',
        type: 'array{success: true, message: string, data: PlanResource[], meta: null, errors: null}',
    )]
    public function index(): JsonResponse
    {
        return $this->success(
            PlanResource::collection($this->planService->listPublic()),
            'Plans retrieved successfully.',
        );
    }

    /**
     * Show a publicly available plan.
     */
    #[Response(
        status: 200,
        description: 'A public plan.',
        type: 'array{success: true, message: string, data: PlanResource, meta: null, errors: null}',
    )]
    public function show(Plan $plan): JsonResponse
    {
        return $this->success(
            new PlanResource($this->planService->showPublic($plan)),
            'Plan retrieved successfully.',
        );
    }
}
