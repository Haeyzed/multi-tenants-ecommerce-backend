<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexInterviewRequest;
use App\Http\Requests\Tenant\HR\StoreInterviewFeedbackRequest;
use App\Http\Requests\Tenant\HR\StoreInterviewRequest;
use App\Http\Requests\Tenant\HR\UpdateInterviewRequest;
use App\Http\Resources\Tenant\HR\InterviewFeedbackResource;
use App\Http\Resources\Tenant\HR\InterviewResource;
use App\Models\HR\Interview;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\InterviewService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant InterviewController endpoints.
 */
#[Group('HR / Interviews')]
class InterviewController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  InterviewService  $interviews
     */
    public function __construct(private readonly InterviewService $interviews) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexInterviewRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated interviews.', type: 'array{success: true, message: string, data: InterviewResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexInterviewRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Interview::class);

        /** @var User $user */
        $user = $request->user();
        $params = $request->validated();

        if (
            ! empty($params['mine'])
            || (
                ! $user->can('hr.recruitment.view')
                && ! $user->can('hr.recruitment.manage')
                && ! $user->can('hr.view')
            )
        ) {
            $params['interviewer_id'] = $user->id;
        }

        $interviews = $this->interviews->list($params);

        return $this->success(
            InterviewResource::collection($interviews->items()),
            'Interviews retrieved successfully.',
            $this->paginationMeta($interviews),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreInterviewRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Scheduled interview.', type: 'array{success: true, message: string, data: InterviewResource, meta: null, errors: null}')]
    public function store(StoreInterviewRequest $request): JsonResponse
    {
        $this->authorize('create', Interview::class);

        return $this->created(
            new InterviewResource($this->interviews->store($request->validated())),
            'Interview scheduled successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Interview  $interview
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'An interview.', type: 'array{success: true, message: string, data: InterviewResource, meta: null, errors: null}')]
    public function show(Interview $interview): JsonResponse
    {
        $this->authorize('view', $interview);

        return $this->success(
            new InterviewResource($this->interviews->show($interview)),
            'Interview retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateInterviewRequest  $request
     * @param  Interview  $interview
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated interview.', type: 'array{success: true, message: string, data: InterviewResource, meta: null, errors: null}')]
    public function update(UpdateInterviewRequest $request, Interview $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        return $this->updated(
            new InterviewResource($this->interviews->update($interview, $request->validated())),
            'Interview updated successfully.',
        );
    }

    /**
     * Complete.
     *
     * @param  Interview  $interview
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Completed interview.', type: 'array{success: true, message: string, data: InterviewResource, meta: null, errors: null}')]
    public function complete(Interview $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        return $this->updated(
            new InterviewResource($this->interviews->complete($interview)),
            'Interview completed successfully.',
        );
    }

    /**
     * Cancel.
     *
     * @param  Interview  $interview
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Cancelled interview.', type: 'array{success: true, message: string, data: InterviewResource, meta: null, errors: null}')]
    public function cancel(Interview $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        return $this->updated(
            new InterviewResource($this->interviews->cancel($interview)),
            'Interview cancelled successfully.',
        );
    }

    /**
     * Feedback.
     *
     * @param  StoreInterviewFeedbackRequest  $request
     * @param  Interview  $interview
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Interview feedback.', type: 'array{success: true, message: string, data: InterviewFeedbackResource, meta: null, errors: null}')]
    public function feedback(StoreInterviewFeedbackRequest $request, Interview $interview): JsonResponse
    {
        $this->authorize('feedback', $interview);

        /** @var User $user */
        $user = $request->user();

        return $this->created(
            new InterviewFeedbackResource($this->interviews->submitFeedback($interview, $user, $request->validated())),
            'Interview feedback submitted successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  Interview  $interview
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted interview.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Interview $interview): JsonResponse
    {
        $this->authorize('delete', $interview);
        $this->interviews->destroy($interview);

        return $this->deleted('Interview deleted successfully.');
    }
}
