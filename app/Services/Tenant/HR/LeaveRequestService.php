<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\LeaveStatus;
use App\Events\LeaveRequested;
use App\Events\LeaveReviewed;
use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\HR\LeaveBalance;
use App\Models\Tenant\HR\LeaveRequest;
use App\Models\Tenant\HR\LeaveType;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Employee leave requests, balances, and review workflow.
 */
class LeaveRequestService
{
    /**
     * Create a new class instance.
     *
     * @param  HrSettingsService  $hrSettings
     * @param  LeaveTypeService  $leaveTypeService
     * @param  WorkCalendarService  $calendar
     * @param  HrActivityService  $activities
     */
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly LeaveTypeService $leaveTypeService,
        private readonly WorkCalendarService $calendar,
        private readonly HrActivityService $activities,
    ) {}

    /**
     * employee_id?: int|null, type?: string|null, status?: string|null, from?: string|null, to?: string|null, sort?: string|null, per_page?: int|null }  $params
     *
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
            ->with(['employee.user', 'reviewer', 'leaveType'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * employee_id: int, leave_type_id?: int|null, type?: string|null, start_date: string, end_date: string, reason?: string|null }  $data
     *
     * @param  array{
     *     employee_id: int,
     *     leave_type_id?: int|null,
     *     type?: string|null,
     *     start_date: string,
     *     end_date: string,
     *     reason?: string|null
     * }  $data
     * @return LeaveRequest
     *
     * @throws ValidationException
     */
    public function store(array $data): LeaveRequest
    {
        $this->hrSettings->assertLeaveEnabled();

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $leaveType = $this->resolveLeaveType($data);

        if ($data['end_date'] < $data['start_date']) {
            throw ValidationException::withMessages([
                'end_date' => ['The leave end date must be on or after the start date.'],
            ]);
        }

        $days = $this->countWorkingDays($data['start_date'], $data['end_date'], $employee);
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
            'leave_type_id' => $leaveType->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $autoApprove ? LeaveStatus::Approved : LeaveStatus::Pending,
            'reason' => $data['reason'] ?? null,
            'reviewed_at' => $autoApprove ? now() : null,
        ])->load(['employee.user', 'reviewer', 'leaveType']);

        $this->activities->record($leaveRequest, 'requested', null, [
            'status' => $leaveRequest->status->value,
            'leave_type_id' => $leaveType->id,
        ]);

        event(new LeaveRequested($leaveRequest));

        if ($autoApprove) {
            $this->consumeBalance($employee, $leaveType, $data['start_date'], $days);
            event(new LeaveReviewed($leaveRequest));
        }

        return $leaveRequest;
    }

    /**
     * Retrieve a single resource.
     *
     * @param  LeaveRequest  $leaveRequest
     * @return LeaveRequest
     */
    public function show(LeaveRequest $leaveRequest): LeaveRequest
    {
        return $leaveRequest->load(['employee.user', 'reviewer', 'leaveType']);
    }

    /**
     * Approve or reject a pending leave request.
     *
     * @param  LeaveRequest  $leaveRequest
     * @param  LeaveStatus  $status
     * @param  User  $reviewer
     * @param  ?string  $notes
     * @return LeaveRequest
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

            $leaveType = $leaveRequest->leaveType ?? $this->leaveTypeService->findActiveById((int) $leaveRequest->leave_type_id);
            $days = $this->countWorkingDays(
                $leaveRequest->start_date->toDateString(),
                $leaveRequest->end_date->toDateString(),
                $leaveRequest->employee,
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

        $leaveRequest = $leaveRequest->fresh(['employee.user', 'reviewer', 'leaveType']) ?? $leaveRequest;

        $this->activities->record($leaveRequest, $status->value, $reviewer, [
            'leave_type_id' => $leaveRequest->leave_type_id,
        ]);

        event(new LeaveReviewed($leaveRequest));

        return $leaveRequest;
    }

    /**
     * Cancel a pending leave request.
     *
     * @param  LeaveRequest  $leaveRequest
     * @return LeaveRequest
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

        $leaveRequest = $leaveRequest->fresh(['employee.user', 'reviewer', 'leaveType']) ?? $leaveRequest;

        $this->activities->record($leaveRequest, 'cancelled', null, [
            'leave_type_id' => $leaveRequest->leave_type_id,
        ]);

        return $leaveRequest;
    }

    /**
     * Balances for.
     *
     * @param  Employee  $employee
     * @param  ?int  $year
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
     * Resolve leave type.
     *
     * @param  array{leave_type_id?: int|null, type?: string|null}  $data
     * @return LeaveType
     *
     * @throws ValidationException
     */
    protected function resolveLeaveType(array $data): LeaveType
    {
        if (array_key_exists('leave_type_id', $data) && $data['leave_type_id'] !== null && $data['leave_type_id'] !== '') {
            return $this->leaveTypeService->findActiveById((int) $data['leave_type_id']);
        }

        if (array_key_exists('type', $data) && is_string($data['type']) && $data['type'] !== '') {
            return $this->leaveTypeService->findActiveByCode($data['type']);
        }

        throw ValidationException::withMessages([
            'leave_type_id' => ['A leave type is required.'],
        ]);
    }

    /**
     * Assert no approved overlap.
     *
     * @param  Employee  $employee
     * @param  string  $startDate
     * @param  string  $endDate
     * @param  ?int  $ignoreId
     * @return void
     *
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
     * Assert balance available.
     *
     * @param  Employee  $employee
     * @param  LeaveType  $leaveType
     * @param  string  $startDate
     * @param  int  $days
     * @return void
     *
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

    /**
     * Consume balance.
     *
     * @param  Employee  $employee
     * @param  LeaveType  $leaveType
     * @param  string  $startDate
     * @param  int  $days
     * @return void
     */
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

    /**
     * Balance for.
     *
     * @param  Employee  $employee
     * @param  LeaveType  $leaveType
     * @param  int  $year
     * @return LeaveBalance
     */
    protected function balanceFor(Employee $employee, LeaveType $leaveType, int $year): LeaveBalance
    {
        $existing = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $carriedOver = $this->carryOverDays($employee, $leaveType, $year);

        return LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $year,
            'entitled' => $leaveType->default_days,
            'carried_over' => $carriedOver,
            'used' => 0,
        ]);
    }

    /**
     * Carry over days.
     *
     * @param  Employee  $employee
     * @param  LeaveType  $leaveType
     * @param  int  $year
     * @return int
     */
    protected function carryOverDays(Employee $employee, LeaveType $leaveType, int $year): int
    {
        if (! $this->hrSettings->leaveCarryOverEnabled() || ! $leaveType->allow_carry_over) {
            return 0;
        }

        $previous = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year - 1)
            ->first();

        if ($previous === null) {
            return 0;
        }

        $remaining = $previous->remaining();
        $max = $this->hrSettings->leaveCarryOverMaxDays();

        if ($max > 0) {
            $remaining = min($remaining, $max);
        }

        return $remaining;
    }

    /**
     * Count working days.
     *
     * @param  string  $startDate
     * @param  string  $endDate
     * @param  ?Employee  $employee
     * @return int
     */
    protected function countWorkingDays(string $startDate, string $endDate, ?Employee $employee = null): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $count = 0;

        while ($start->lte($end)) {
            if ($this->calendar->isWorkingDate($employee, $start) && ! $this->calendar->isPublicHoliday($start)) {
                $count++;
            }

            $start->addDay();
        }

        return $count;
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
