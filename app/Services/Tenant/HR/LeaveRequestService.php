<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\LeaveType;
use App\Events\LeaveRequested;
use App\Events\LeaveReviewed;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Employee leave requests and review workflow.
 */
class LeaveRequestService
{
    /**
     * @param  array{
     *     employee_id?: int|null,
     *     type?: string|null,
     *     status?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, LeaveRequest>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return LeaveRequest::query()
            ->with(['employee.user', 'reviewer'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     employee_id: int,
     *     type: LeaveType|string,
     *     start_date: string,
     *     end_date: string,
     *     reason?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): LeaveRequest
    {
        $employee = Employee::query()->findOrFail($data['employee_id']);

        if ($data['end_date'] < $data['start_date']) {
            throw ValidationException::withMessages([
                'end_date' => ['The leave end date must be on or after the start date.'],
            ]);
        }

        $this->assertNoApprovedOverlap($employee, $data['start_date'], $data['end_date']);

        $leaveRequest = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => LeaveStatus::Pending,
            'reason' => $data['reason'] ?? null,
        ])->load(['employee.user', 'reviewer']);

        event(new LeaveRequested($leaveRequest));

        return $leaveRequest;
    }

    public function show(LeaveRequest $leaveRequest): LeaveRequest
    {
        return $leaveRequest->load(['employee.user', 'reviewer']);
    }

    /**
     * Approve or reject a pending leave request.
     *
     * @throws ValidationException
     */
    public function review(LeaveRequest $leaveRequest, LeaveStatus $status, User $reviewer, ?string $notes = null): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Only pending leave requests can be reviewed.'],
            ]);
        }

        if (! in_array($status, [LeaveStatus::Approved, LeaveStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'status' => ['Leave review must approve or reject the request.'],
            ]);
        }

        if ($status === LeaveStatus::Approved) {
            $this->assertNoApprovedOverlap(
                $leaveRequest->employee,
                $leaveRequest->start_date->toDateString(),
                $leaveRequest->end_date->toDateString(),
                $leaveRequest->id,
            );
        }

        $leaveRequest->fill([
            'status' => $status,
            'reviewer_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
        $leaveRequest->save();

        $leaveRequest = $leaveRequest->fresh(['employee.user', 'reviewer']) ?? $leaveRequest;

        event(new LeaveReviewed($leaveRequest));

        return $leaveRequest;
    }

    /**
     * Cancel a pending leave request.
     *
     * @throws ValidationException
     */
    public function cancel(LeaveRequest $leaveRequest): LeaveRequest
    {
        if ($leaveRequest->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Only pending leave requests can be cancelled.'],
            ]);
        }

        $leaveRequest->status = LeaveStatus::Cancelled;
        $leaveRequest->save();

        return $leaveRequest->fresh(['employee.user', 'reviewer']) ?? $leaveRequest;
    }

    /**
     * @throws ValidationException
     */
    protected function assertNoApprovedOverlap(Employee $employee, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $overlaps = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => ['This leave period overlaps an already approved request.'],
            ]);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
