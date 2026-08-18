<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Daily attendance records and clock in/out.
 */
class AttendanceService
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * @param  array{
     *     employee_id?: int|null,
     *     status?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Attendance>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Attendance::query()
            ->with(['employee.user'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     employee_id: int,
     *     work_date?: string|null,
     *     status?: AttendanceStatus|string|null,
     *     checked_in_at?: string|null,
     *     checked_out_at?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function store(array $data): Attendance
    {
        $this->hrSettings->assertAttendanceEnabled();

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $workDate = $data['work_date'] ?? now()->toDateString();

        if (Attendance::query()->where('employee_id', $employee->id)->whereDate('work_date', $workDate)->exists()) {
            throw ValidationException::withMessages([
                'work_date' => ['An attendance record already exists for this employee on that date.'],
            ]);
        }

        return Attendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'status' => $data['status'] ?? AttendanceStatus::Present,
            'checked_in_at' => $data['checked_in_at'] ?? null,
            'checked_out_at' => $data['checked_out_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['employee.user']);
    }

    public function show(Attendance $attendance): Attendance
    {
        return $attendance->load(['employee.user']);
    }

    /**
     * @param  array{
     *     status?: AttendanceStatus|string,
     *     checked_in_at?: string|null,
     *     checked_out_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function update(Attendance $attendance, array $data): Attendance
    {
        $this->hrSettings->assertAttendanceEnabled();

        unset($data['employee_id'], $data['work_date']);

        $attendance->fill($data);
        $attendance->save();

        return $attendance->fresh(['employee.user']) ?? $attendance;
    }

    public function destroy(Attendance $attendance): void
    {
        $this->hrSettings->assertAttendanceEnabled();

        $attendance->delete();
    }

    /**
     * Clock the employee in for today.
     *
     * @throws ValidationException
     */
    public function clockIn(Employee $employee): Attendance
    {
        $this->hrSettings->assertAttendanceEnabled();

        $now = now();

        if (! $this->hrSettings->isWorkingDate($now)) {
            throw ValidationException::withMessages([
                'employee_id' => ['Today is not a configured working day.'],
            ]);
        }

        $today = $now->toDateString();
        $existing = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->first();

        if ($existing !== null && $existing->checked_in_at !== null) {
            throw ValidationException::withMessages([
                'employee_id' => ['This employee is already clocked in for today.'],
            ]);
        }

        $status = $this->clockInStatus($now);

        if ($existing !== null) {
            $existing->fill([
                'status' => $status,
                'checked_in_at' => $now,
            ]);
            $existing->save();

            return $existing->fresh(['employee.user']) ?? $existing;
        }

        return Attendance::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $today,
            'status' => $status,
            'checked_in_at' => $now,
        ])->load(['employee.user']);
    }

    /**
     * Clock the employee out for today.
     *
     * @throws ValidationException
     */
    public function clockOut(Employee $employee): Attendance
    {
        $this->hrSettings->assertAttendanceEnabled();

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        if ($attendance === null || $attendance->checked_in_at === null) {
            throw ValidationException::withMessages([
                'employee_id' => ['This employee is not clocked in for today.'],
            ]);
        }

        if ($attendance->checked_out_at !== null) {
            throw ValidationException::withMessages([
                'employee_id' => ['This employee is already clocked out for today.'],
            ]);
        }

        $attendance->checked_out_at = now();
        $attendance->save();

        return $attendance->fresh(['employee.user']) ?? $attendance;
    }

    protected function clockInStatus(Carbon $now): AttendanceStatus
    {
        $start = Carbon::parse($now->toDateString().' '.$this->hrSettings->workStartTime());
        $threshold = $start->copy()->addMinutes($this->hrSettings->lateToleranceMinutes());

        return $now->gt($threshold) ? AttendanceStatus::Late : AttendanceStatus::Present;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
