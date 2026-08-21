<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Enums\Tenant\HR\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexLeaveRequestRequest;
use App\Http\Requests\Tenant\HR\ReviewLeaveRequestRequest;
use App\Http\Requests\Tenant\HR\StoreLeaveRequestRequest;
use App\Http\Resources\Tenant\HR\LeaveRequestResource;
use App\Models\HR\LeaveRequest;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\LeaveRequestService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Tenant HR leave request endpoints.
 */
#[Group('HR')]
class LeaveRequestController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  LeaveRequestService  $leaveRequestService
     */
    public function __construct(private readonly LeaveRequestService $leaveRequestService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexLeaveRequestRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated leave requests.', type: 'array{success: true, message: string, data: LeaveRequestResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexLeaveRequestRequest $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $params = $request->validated();
        $actor = $this->actor();

        if (! $actor->can('hr.leave.view') && ! $actor->can('hr.leave.manage') && ! $actor->can('hr.view')) {
            $params['employee_id'] = $actor->employee?->id;
        }

        $leaveRequests = $this->leaveRequestService->list($params);

        return $this->success(
            LeaveRequestResource::collection($leaveRequests->items()),
            'Leave requests retrieved successfully.',
            $this->paginationMeta($leaveRequests),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreLeaveRequestRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created leave request.', type: 'array{success: true, message: string, data: LeaveRequestResource, meta: null, errors: null}')]
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $data = $request->validated();
        $actor = $this->actor();

        if (! $actor->can('hr.leave.manage') && (int) $data['employee_id'] !== $actor->employee?->id) {
            throw ValidationException::withMessages([
                'employee_id' => ['You may only submit leave for your own employee profile.'],
            ]);
        }

        return $this->created(
            new LeaveRequestResource($this->leaveRequestService->store($data)),
            'Leave request submitted successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  LeaveRequest  $leave_request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A leave request.', type: 'array{success: true, message: string, data: LeaveRequestResource, meta: null, errors: null}')]
    public function show(LeaveRequest $leave_request): JsonResponse
    {
        $this->authorize('view', $leave_request);

        return $this->success(
            new LeaveRequestResource($this->leaveRequestService->show($leave_request)),
            'Leave request retrieved successfully.',
        );
    }

    /**
     * Approve.
     *
     * @param  ReviewLeaveRequestRequest  $request
     * @param  LeaveRequest  $leave_request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Approved leave request.', type: 'array{success: true, message: string, data: LeaveRequestResource, meta: null, errors: null}')]
    public function approve(ReviewLeaveRequestRequest $request, LeaveRequest $leave_request): JsonResponse
    {
        $this->authorize('review', $leave_request);

        return $this->updated(
            new LeaveRequestResource($this->leaveRequestService->review(
                $leave_request,
                LeaveStatus::Approved,
                $this->actor(),
                $request->validated('review_notes'),
            )),
            'Leave request approved successfully.',
        );
    }

    /**
     * Reject.
     *
     * @param  ReviewLeaveRequestRequest  $request
     * @param  LeaveRequest  $leave_request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Rejected leave request.', type: 'array{success: true, message: string, data: LeaveRequestResource, meta: null, errors: null}')]
    public function reject(ReviewLeaveRequestRequest $request, LeaveRequest $leave_request): JsonResponse
    {
        $this->authorize('review', $leave_request);

        return $this->updated(
            new LeaveRequestResource($this->leaveRequestService->review(
                $leave_request,
                LeaveStatus::Rejected,
                $this->actor(),
                $request->validated('review_notes'),
            )),
            'Leave request rejected successfully.',
        );
    }

    /**
     * Cancel.
     *
     * @param  LeaveRequest  $leave_request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Cancelled leave request.', type: 'array{success: true, message: string, data: LeaveRequestResource, meta: null, errors: null}')]
    public function cancel(LeaveRequest $leave_request): JsonResponse
    {
        $this->authorize('cancel', $leave_request);

        return $this->updated(
            new LeaveRequestResource($this->leaveRequestService->cancel($leave_request)),
            'Leave request cancelled successfully.',
        );
    }

    /**
     * Resolve the authenticated tenant user.
     *
     * @return User
     */
    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        return $user;
    }
}
