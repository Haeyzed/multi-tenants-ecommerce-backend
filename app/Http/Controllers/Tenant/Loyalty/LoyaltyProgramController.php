<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Loyalty\UpdateLoyaltyProgramRequest;
use App\Http\Resources\Tenant\Loyalty\LoyaltyProgramResource;
use App\Services\Tenant\Loyalty\LoyaltyService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Staff loyalty program settings.
 */
class LoyaltyProgramController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    #[Response(status: 200, description: 'Loyalty program settings.', type: 'array{success: true, message: string, data: LoyaltyProgramResource, meta: null, errors: null}')]
    public function show(): JsonResponse
    {
        $program = $this->loyalty->ensureProgram();
        $this->authorize('view', $program);

        return $this->success(
            new LoyaltyProgramResource($program),
            'Loyalty program retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated loyalty program.', type: 'array{success: true, message: string, data: LoyaltyProgramResource, meta: null, errors: null}')]
    public function update(UpdateLoyaltyProgramRequest $request): JsonResponse
    {
        $this->authorize('update', $this->loyalty->ensureProgram());

        return $this->updated(
            new LoyaltyProgramResource($this->loyalty->updateProgram($request->validated())),
            'Loyalty program updated successfully.',
        );
    }
}
