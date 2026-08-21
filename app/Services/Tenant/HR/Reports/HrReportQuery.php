<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR\Reports;

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\JobApplication;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollItem;
use App\Models\HR\PayrollItemLine;
use App\Services\Tenant\HR\PayrollPeriodService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Shared date-windowed HR report queries. Aggregation stays in report/summary services.
 */
class HrReportQuery
{
    /**
     * Create a new class instance.
     *
     * @param  PayrollPeriodService  $periods
     */
    public function __construct(private readonly PayrollPeriodService $periods) {}

    /**
     * Period window.
     *
     * @param  ?Carbon  $asOf
     * @return array{period_start: string, period_end: string, payment_date: string}
     */
    public function periodWindow(?Carbon $asOf = null): array
    {
        return $this->periods->periodWindow($asOf);
    }

    /**
     * Date window.
     *
     * @param  array{from?: string|null, to?: string|null}  $params
     * @return array{0: string, 1: string}
     */
    public function dateWindow(array $params): array
    {
        if (! empty($params['from']) && ! empty($params['to'])) {
            return [(string) $params['from'], (string) $params['to']];
        }

        $period = $this->periodWindow(
            isset($params['from']) ? Carbon::parse((string) $params['from']) : now(),
        );

        return [$period['period_start'], $period['period_end']];
    }

    /**
     * Employees.
     *
     * @param  array{department_id?: int|null, employee_id?: int|null}  $params
     * @return Builder<Employee>
     */
    public function employees(array $params = []): Builder
    {
        return Employee::query()
            ->when($params['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->when($params['employee_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id));
    }

    /**
     * Employees with attendance counts for a date window, without loading attendance rows.
     *
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null, employee_id?: int|null}  $params
     * @param  string  $from
     * @param  string  $to
     * @return Builder<Employee>
     */
    public function employeesWithAttendanceTotals(array $params, string $from, string $to): Builder
    {
        $inWindow = fn ($query) => $query
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to);

        return $this->employees($params)
            ->with('user:id,first_name,last_name,email')
            ->withCount([
                'attendances as present_count' => fn ($query) => $inWindow($query)->where('status', AttendanceStatus::Present),
                'attendances as late_count' => fn ($query) => $inWindow($query)->where('status', AttendanceStatus::Late),
                'attendances as absent_count' => fn ($query) => $inWindow($query)->where('status', AttendanceStatus::Absent),
            ])
            ->withSum([
                'attendances as overtime_minutes_sum' => $inWindow,
            ], 'overtime_minutes');
    }

    /**
     * Leave requests.
     *
     * @param  array{department_id?: int|null, employee_id?: int|null}  $params
     * @param  string  $from
     * @param  string  $to
     * @return Builder<LeaveRequest>
     */
    public function leaveRequests(array $params, string $from, string $to): Builder
    {
        return LeaveRequest::query()
            ->with(['employee.user:id,first_name,last_name', 'leaveType'])
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->when($params['department_id'] ?? null, function (Builder $query, int $id): void {
                $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $id));
            })
            ->when($params['employee_id'] ?? null, fn (Builder $query, int $id) => $query->where('employee_id', $id));
    }

    /**
     * Payroll items.
     *
     * @param  array{department_id?: int|null}  $params
     * @param  string  $from
     * @param  string  $to
     * @return Builder<PayrollItem>
     */
    public function payrollItems(array $params, string $from, string $to): Builder
    {
        return PayrollItem::query()
            ->with(['employee.user:id,first_name,last_name', 'employee.department:id,name', 'payrollRun'])
            ->whereHas('payrollRun', function ($query) use ($from, $to): void {
                $query->whereNot('status', PayrollRunStatus::Cancelled)
                    ->whereDate('period_start', '<=', $to)
                    ->whereDate('period_end', '>=', $from);
            })
            ->when($params['department_id'] ?? null, function (Builder $query, int $id): void {
                $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $id));
            });
    }

    /**
     * Overtime attendances.
     *
     * @param  array{department_id?: int|null, employee_id?: int|null}  $params
     * @param  string  $from
     * @param  string  $to
     * @return Builder<Attendance>
     */
    public function overtimeAttendances(array $params, string $from, string $to): Builder
    {
        return Attendance::query()
            ->with(['employee.user:id,first_name,last_name'])
            ->where('overtime_minutes', '>', 0)
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->when($params['department_id'] ?? null, function (Builder $query, int $id): void {
                $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $id));
            })
            ->when($params['employee_id'] ?? null, fn (Builder $query, int $id) => $query->where('employee_id', $id));
    }

    /**
     * Overtime payroll lines.
     *
     * @param  string  $from
     * @param  string  $to
     * @return Builder<PayrollItemLine>
     */
    public function overtimePayrollLines(string $from, string $to): Builder
    {
        return PayrollItemLine::query()
            ->where('code', 'overtime')
            ->whereHas('payrollItem.payrollRun', function ($query) use ($from, $to): void {
                $query->whereNot('status', PayrollRunStatus::Cancelled)
                    ->whereDate('period_start', '<=', $to)
                    ->whereDate('period_end', '>=', $from);
            });
    }

    /**
     * Headcount employees.
     *
     * @param  string  $asOf
     * @return Builder<Employee>
     */
    public function headcountEmployees(string $asOf): Builder
    {
        return Employee::query()
            ->with('department:id,name')
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('hired_at')->orWhereDate('hired_at', '<=', $asOf);
            })
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('terminated_at')->orWhereDate('terminated_at', '>', $asOf);
            });
    }

    /**
     * Applications.
     *
     * @param  string  $from
     * @param  string  $to
     * @return Builder<JobApplication>
     */
    public function applications(string $from, string $to): Builder
    {
        return JobApplication::query()
            ->with('jobOpening:id,title')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);
    }

    /**
     * Attendances in window.
     *
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null, employee_id?: int|null}  $params
     * @param  string  $from
     * @param  string  $to
     * @return Builder<Attendance>
     */
    public function attendancesInWindow(array $params, string $from, string $to): Builder
    {
        return Attendance::query()
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->when($params['department_id'] ?? null, function (Builder $query, int $id): void {
                $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $id));
            })
            ->when($params['employee_id'] ?? null, fn (Builder $query, int $id) => $query->where('employee_id', $id));
    }

    /**
     * Employee display name.
     *
     * @param  ?Employee  $employee
     * @return string
     */
    public function employeeDisplayName(?Employee $employee): string
    {
        return trim(($employee?->user?->first_name ?? '').' '.($employee?->user?->last_name ?? ''));
    }
}
