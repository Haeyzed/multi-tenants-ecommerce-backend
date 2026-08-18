<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexOvertimePolicyRequest;
use App\Http\Requests\Tenant\HR\StoreOvertimePolicyRequest;
use App\Http\Requests\Tenant\HR\UpdateOvertimePolicyRequest;
use App\Http\Resources\Tenant\HR\OvertimePolicyResource;
use App\Models\Tenant\OvertimePolicy;
use App\Services\Tenant\HR\OvertimePolicyService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Overtime rate policies.
 */
#[Group('HR')]
class OvertimePolicyController extends Controller
{
    public function __construct(private readonly OvertimePolicyService $overtimePolicies) {}

    #[Response(status: 200, description: 'Paginated overtime policies.', type: 'array{success: true, message: string, data: OvertimePolicyResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexOvertimePolicyRequest $request): JsonResponse
    {
        $this->authorize('viewAny', OvertimePolicy::class);

        $policies = $this->overtimePolicies->list($request->validated());

        return $this->success(
            OvertimePolicyResource::collection($policies->items()),
            'Overtime policies retrieved successfully.',
            $this->paginationMeta($policies),
        );
    }

    #[Response(status: 200, description: 'Overtime policy options.', type: ApiResponseSchema::OPTIONS)]
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', OvertimePolicy::class);

        return $this->success(
            $this->overtimePolicies->options(),
            'Overtime policy options retrieved successfully.',
        );
    }

    #[Response(status: 201, description: 'Created overtime policy.', type: 'array{success: true, message: string, data: OvertimePolicyResource, meta: null, errors: null}')]
    public function store(StoreOvertimePolicyRequest $request): JsonResponse
    {
        $this->authorize('create', OvertimePolicy::class);

        return $this->created(
            new OvertimePolicyResource($this->overtimePolicies->store($request->validated())),
            'Overtime policy created successfully.',
        );
    }

    #[Response(status: 200, description: 'An overtime policy.', type: 'array{success: true, message: string, data: OvertimePolicyResource, meta: null, errors: null}')]
    public function show(OvertimePolicy $overtime_policy): JsonResponse
    {
        $this->authorize('view', $overtime_policy);

        return $this->success(
            new OvertimePolicyResource($this->overtimePolicies->show($overtime_policy)),
            'Overtime policy retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated overtime policy.', type: 'array{success: true, message: string, data: OvertimePolicyResource, meta: null, errors: null}')]
    public function update(UpdateOvertimePolicyRequest $request, OvertimePolicy $overtime_policy): JsonResponse
    {
        $this->authorize('update', $overtime_policy);

        return $this->updated(
            new OvertimePolicyResource($this->overtimePolicies->update($overtime_policy, $request->validated())),
            'Overtime policy updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted overtime policy.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(OvertimePolicy $overtime_policy): JsonResponse
    {
        $this->authorize('delete', $overtime_policy);
        $this->overtimePolicies->destroy($overtime_policy);

        return $this->deleted('Overtime policy deleted successfully.');
    }
}
