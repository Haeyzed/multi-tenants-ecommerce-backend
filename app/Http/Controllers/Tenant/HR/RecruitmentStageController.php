<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\StoreRecruitmentStageRequest;
use App\Http\Requests\Tenant\HR\UpdateRecruitmentStageRequest;
use App\Http\Resources\Tenant\HR\RecruitmentStageResource;
use App\Models\Tenant\HR\RecruitmentStage;
use App\Services\Tenant\HR\RecruitmentStageService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant RecruitmentStageController endpoints.
 */
#[Group('HR / Recruitment')]
class RecruitmentStageController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  RecruitmentStageService  $stages
     */
    public function __construct(private readonly RecruitmentStageService $stages) {}

    /**
     * List resources with pagination and filters.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Recruitment stages.', type: 'array{success: true, message: string, data: RecruitmentStageResource[], meta: null, errors: null}')]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', RecruitmentStage::class);

        return $this->success(
            RecruitmentStageResource::collection($this->stages->list()),
            'Recruitment stages retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreRecruitmentStageRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created recruitment stage.', type: 'array{success: true, message: string, data: RecruitmentStageResource, meta: null, errors: null}')]
    public function store(StoreRecruitmentStageRequest $request): JsonResponse
    {
        $this->authorize('create', RecruitmentStage::class);

        return $this->created(
            new RecruitmentStageResource($this->stages->store($request->validated())),
            'Recruitment stage created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  RecruitmentStage  $recruitment_stage
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A recruitment stage.', type: 'array{success: true, message: string, data: RecruitmentStageResource, meta: null, errors: null}')]
    public function show(RecruitmentStage $recruitment_stage): JsonResponse
    {
        $this->authorize('view', $recruitment_stage);

        return $this->success(
            new RecruitmentStageResource($this->stages->show($recruitment_stage)),
            'Recruitment stage retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateRecruitmentStageRequest  $request
     * @param  RecruitmentStage  $recruitment_stage
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated recruitment stage.', type: 'array{success: true, message: string, data: RecruitmentStageResource, meta: null, errors: null}')]
    public function update(UpdateRecruitmentStageRequest $request, RecruitmentStage $recruitment_stage): JsonResponse
    {
        $this->authorize('update', $recruitment_stage);

        return $this->updated(
            new RecruitmentStageResource($this->stages->update($recruitment_stage, $request->validated())),
            'Recruitment stage updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  RecruitmentStage  $recruitment_stage
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted recruitment stage.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(RecruitmentStage $recruitment_stage): JsonResponse
    {
        $this->authorize('delete', $recruitment_stage);
        $this->stages->destroy($recruitment_stage);

        return $this->deleted('Recruitment stage deleted successfully.');
    }
}
