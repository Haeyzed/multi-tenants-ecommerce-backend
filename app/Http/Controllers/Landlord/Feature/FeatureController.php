<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Feature;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Feature\IndexFeatureRequest;
use App\Http\Requests\Landlord\Feature\StoreFeatureRequest;
use App\Http\Requests\Landlord\Feature\UpdateFeatureRequest;
use App\Http\Resources\Landlord\Feature\FeatureResource;
use App\Models\Landlord\Feature;
use App\Services\Landlord\Feature\FeatureService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord feature catalog endpoints.
 */
class FeatureController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  FeatureService  $featureService
     */
    public function __construct(private readonly FeatureService $featureService) {}

    /**
     * List features.
     *
     * @param  IndexFeatureRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of features.',
        type: 'array{success: true, message: string, data: FeatureResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexFeatureRequest $request): JsonResponse
    {
        $features = $this->featureService->list($request->validated());

        return $this->success(
            FeatureResource::collection($features->items()),
            'Features retrieved successfully.',
            $this->paginationMeta($features),
        );
    }

    /**
     * Return feature options as label/value pairs.
     *
     * @param  IndexFeatureRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Feature options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexFeatureRequest $request): JsonResponse
    {
        return $this->success(
            $this->featureService->options($request->validated()),
            'Feature options retrieved successfully.',
        );
    }

    /**
     * Create a feature.
     *
     * @param  StoreFeatureRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created feature.',
        type: 'array{success: true, message: string, data: FeatureResource, meta: null, errors: null}',
    )]
    public function store(StoreFeatureRequest $request): JsonResponse
    {
        $feature = $this->featureService->store($request->validated());

        return $this->created(
            new FeatureResource($feature),
            'Feature created successfully.',
        );
    }

    /**
     * Show a feature.
     *
     * @param  Feature  $feature
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single feature.',
        type: 'array{success: true, message: string, data: FeatureResource, meta: null, errors: null}',
    )]
    public function show(Feature $feature): JsonResponse
    {
        return $this->success(
            new FeatureResource($this->featureService->show($feature)),
            'Feature retrieved successfully.',
        );
    }

    /**
     * Update a feature.
     *
     * @param  UpdateFeatureRequest  $request
     * @param  Feature  $feature
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated feature.',
        type: 'array{success: true, message: string, data: FeatureResource, meta: null, errors: null}',
    )]
    public function update(UpdateFeatureRequest $request, Feature $feature): JsonResponse
    {
        $feature = $this->featureService->update($feature, $request->validated());

        return $this->updated(
            new FeatureResource($feature),
            'Feature updated successfully.',
        );
    }

    /**
     * Delete a feature.
     *
     * @param  Feature  $feature
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Deleted feature confirmation.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Feature $feature): JsonResponse
    {
        $this->featureService->destroy($feature);

        return $this->deleted('Feature deleted successfully.');
    }
}
