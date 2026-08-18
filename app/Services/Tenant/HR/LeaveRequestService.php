<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\LeaveStatus;
use App\Events\LeaveRequested;
use App\Events\LeaveReviewed;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveBalance;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Employee leave requests, balances, and review workflow.
 */
class LeaveRequestService
{
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly LeaveTypeService $leaveTypeService,
    ) {}

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
     *     type: string,
     *     start_date: string,
     *     end_date: string,
     *     reason?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): LeaveRequest
    {
        $this->hrSettings->assertLeaveEnabled();

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $leaveType = $this->leaveTypeService->findActiveByCode((string) $data['type']);

        if ($data['end_date'] < $data['start_date']) {
            throw ValidationException::withMessages([
                'end_date' => ['The leave end date must be on or after the start date.'],
            ]);
        }

        $days = $this->countWorkingDays($data['start_date'], $data['end_date']);
        $maxConsecutive = $this->hrSettings->maxConsecutiveLeaveDays();

        if ($maxConsecutive > 0 && $days > $maxConsecutive) {
            throw ValidationException::withMessages([
                'end_date' => ["Leave cannot exceed {$maxConsecutive} consecutive working days."],
            ]);
        }

        $this->assertNoApprovedOverlap($employee, $data['start_date'], $data['end_date']);
        $this->assertBalanceAvailable($employee, $leaveType, $data['start_date'], $days);

        $autoApprove = ! $this->hrSettings->leaveApprovalRequired();

        $leaveRequest = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'type' => $leaveType->code,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $autoApprove ? LeaveStatus::Approved : LeaveStatus::Pending,
            'reason' => $data['reason'] ?? null,
            'reviewed_at' => $autoApprove ? now() : null,
        ])->load(['employee.user', 'reviewer']);

        event(new LeaveRequested($leaveRequest));

        if ($autoApprove) {
            $this->consumeBalance($employee, $leaveType, $data['start_date'], $days);
            event(new LeaveReviewed($leaveRequest));
        }

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
        $this->hrSettings->assertLeaveEnabled();

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

            $leaveType = $this->leaveTypeService->findActiveByCode($leaveRequest->type);
            $days = $this->countWorkingDays(
                $leaveRequest->start_date->toDateString(),
                $leaveRequest->end_date->toDateString(),
            );
            $this->assertBalanceAvailable($leaveRequest->employee, $leaveType, $leaveRequest->start_date->toDateString(), $days);
            $this->consumeBalance($leaveRequest->employee, $leaveType, $leaveRequest->start_date->toDateString(), $days);
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
        $this->hrSettings->assertLeaveEnabled();

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
     * @return list<LeaveBalance>
     */
    public function balancesFor(Employee $employee, ?int $year = null): array
    {
        $this->leaveTypeService->ensureDefaults();

        $year ??= $this->hrSettings->leaveYearForDate(now());

        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();
        $balances = [];

        foreach ($types as $type) {
            $balances[] = $this->balanceFor($employee, $type, $year)->load('leaveType');
        }

        return $balances;
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
     * @throws ValidationException
     */
    protected function assertBalanceAvailable(Employee $employee, LeaveType $leaveType, string $startDate, int $days): void
    {
        if ($leaveType->default_days <= 0) {
            return;
        }

        $year = $this->hrSettings->leaveYearForDate(Carbon::parse($startDate));
        $balance = $this->balanceFor($employee, $leaveType, $year);

        if ($days > $balance->remaining()) {
            throw ValidationException::withMessages([
                'type' => ["Insufficient {$leaveType->name} leave balance ({$balance->remaining()} day(s) remaining)."],
            ]);
        }
    }

    protected function consumeBalance(Employee $employee, LeaveType $leaveType, string $startDate, int $days): void
    {
        if ($leaveType->default_days <= 0 || $days <= 0) {
            return;
        }

        $year = $this->hrSettings->leaveYearForDate(Carbon::parse($startDate));
        $balance = $this->balanceFor($employee, $leaveType, $year);
        $balance->used += $days;
        $balance->save();
    }

    protected function balanceFor(Employee $employee, LeaveType $leaveType, int $year): LeaveBalance
    {
        return LeaveBalance::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ],
            [
                'entitled' => $leaveType->default_days,
                'used' => 0,
            ],
        );
    }

    protected function countWorkingDays(string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $count = 0;

        while ($start->lte($end)) {
            if ($this->hrSettings->isWorkingDate($start)) {
                $count++;
            }

            $start->addDay();
        }

        return $count;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
