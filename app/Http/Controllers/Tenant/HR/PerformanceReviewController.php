<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexPerformanceReviewRequest;
use App\Http\Requests\Tenant\HR\StorePerformanceReviewRequest;
use App\Http\Requests\Tenant\HR\UpdatePerformanceReviewRequest;
use App\Http\Resources\Tenant\HR\PerformanceReviewResource;
use App\Models\Tenant\HR\PerformanceReview;
use App\Services\Tenant\HR\PerformanceReviewService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Employee performance reviews.
 */
#[Group('HR')]
class PerformanceReviewController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PerformanceReviewService  $reviews
     */
    public function __construct(private readonly PerformanceReviewService $reviews) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexPerformanceReviewRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated performance reviews.', type: 'array{success: true, message: string, data: PerformanceReviewResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexPerformanceReviewRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PerformanceReview::class);

        $reviews = $this->reviews->list($request->validated());

        return $this->success(
            PerformanceReviewResource::collection($reviews->items()),
            'Performance reviews retrieved successfully.',
            $this->paginationMeta($reviews),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StorePerformanceReviewRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created performance review.', type: 'array{success: true, message: string, data: PerformanceReviewResource, meta: null, errors: null}')]
    public function store(StorePerformanceReviewRequest $request): JsonResponse
    {
        $this->authorize('create', PerformanceReview::class);

        return $this->created(
            new PerformanceReviewResource($this->reviews->store($request->validated())),
            'Performance review created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  PerformanceReview  $performance_review
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A performance review.', type: 'array{success: true, message: string, data: PerformanceReviewResource, meta: null, errors: null}')]
    public function show(PerformanceReview $performance_review): JsonResponse
    {
        $this->authorize('view', $performance_review);

        return $this->success(
            new PerformanceReviewResource($this->reviews->show($performance_review)),
            'Performance review retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdatePerformanceReviewRequest  $request
     * @param  PerformanceReview  $performance_review
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated performance review.', type: 'array{success: true, message: string, data: PerformanceReviewResource, meta: null, errors: null}')]
    public function update(UpdatePerformanceReviewRequest $request, PerformanceReview $performance_review): JsonResponse
    {
        $this->authorize('update', $performance_review);

        return $this->updated(
            new PerformanceReviewResource($this->reviews->update($performance_review, $request->validated())),
            'Performance review updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  PerformanceReview  $performance_review
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted performance review.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(PerformanceReview $performance_review): JsonResponse
    {
        $this->authorize('delete', $performance_review);
        $this->reviews->destroy($performance_review);

        return $this->deleted('Performance review deleted successfully.');
    }
}
