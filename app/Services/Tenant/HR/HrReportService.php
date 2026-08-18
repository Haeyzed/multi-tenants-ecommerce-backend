<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\AttendanceStatus;
use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\JobOpening;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\PayrollItem;
use App\Models\Tenant\PayrollItemLine;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Detailed HR operational reports for a date window.
 */
class HrReportService
{
    public function __construct(private readonly PayrollRunService $payrollRuns) {}

    /**
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null, employee_id?: int|null}  $params
     * @return array<string, mixed>
     */
    public function attendance(array $params = []): array
    {
        [$from, $to] = $this->window($params);

        $rows = Employee::query()
            ->with([
                'user:id,first_name,last_name,email',
                'attendances' => fn ($query) => $query
                    ->whereDate('work_date', '>=', $from)
                    ->whereDate('work_date', '<=', $to),
            ])
            ->when($params['department_id'] ?? null, fn ($query, int $id) => $query->where('department_id', $id))
            ->when($params['employee_id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get()
            ->map(function (Employee $employee): array {
                $records = $employee->attendances;

                return [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => trim(($employee->user?->first_name ?? '').' '.($employee->user?->last_name ?? '')),
                    'department_id' => $employee->department_id,
                    'present' => $records->where('status', AttendanceStatus::Present)->count(),
                    'late' => $records->where('status', AttendanceStatus::Late)->count(),
                    'absent' => $records->where('status', AttendanceStatus::Absent)->count(),
                    'overtime_minutes' => (int) $records->sum('overtime_minutes'),
                ];
            })
            ->values()
            ->all();

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'present' => array_sum(array_column($rows, 'present')),
                'late' => array_sum(array_column($rows, 'late')),
                'absent' => array_sum(array_column($rows, 'absent')),
                'overtime_minutes' => array_sum(array_column($rows, 'overtime_minutes')),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null, employee_id?: int|null}  $params
     * @return array<string, mixed>
     */
    public function leave(array $params = []): array
    {
        [$from, $to] = $this->window($params);

        $query = LeaveRequest::query()
            ->with(['employee.user:id,first_name,last_name'])
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->when($params['department_id'] ?? null, function ($query, int $id): void {
                $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $id));
            })
            ->when($params['employee_id'] ?? null, fn ($query, int $id) => $query->where('employee_id', $id));

        $requests = $query->orderByDesc('id')->get();

        $byType = [];

        foreach ($requests as $request) {
            $type = $request->type;
            $byType[$type] ??= ['type' => $type, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
            $status = $request->status instanceof LeaveStatus ? $request->status->value : (string) $request->status;
            $byType[$type][$status] = ($byType[$type][$status] ?? 0) + 1;
        }

        return [
            'from' => $from,
            'to' => $to,
            'by_type' => array_values($byType),
            'rows' => $requests->map(fn (LeaveRequest $request): array => [
                'id' => $request->id,
                'employee_id' => $request->employee_id,
                'name' => trim(($request->employee?->user?->first_name ?? '').' '.($request->employee?->user?->last_name ?? '')),
                'type' => $request->type,
                'status' => $request->status,
                'start_date' => $request->start_date?->toDateString(),
                'end_date' => $request->end_date?->toDateString(),
            ])->all(),
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null}  $params
     * @return array<string, mixed>
     */
    public function payroll(array $params = []): array
    {
        [$from, $to] = $this->window($params);

        $items = PayrollItem::query()
            ->with(['employee.user:id,first_name,last_name', 'employee.department:id,name', 'payrollRun'])
            ->whereHas('payrollRun', function ($query) use ($from, $to): void {
                $query->whereNot('status', PayrollRunStatus::Cancelled)
                    ->whereDate('period_start', '<=', $to)
                    ->whereDate('period_end', '>=', $from);
            })
            ->when($params['department_id'] ?? null, function ($query, int $id): void {
                $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $id));
            })
            ->orderByDesc('id')
            ->get();

        $gross = '0.00';
        $deductions = '0.00';
        $net = '0.00';
        $byDepartment = [];

        $rows = $items->map(function (PayrollItem $item) use (&$gross, &$deductions, &$net, &$byDepartment): array {
            $gross = Money::add($gross, (string) $item->gross_pay);
            $deductions = Money::add($deductions, (string) $item->deduction_total);
            $net = Money::add($net, (string) $item->net_pay);

            $departmentName = $item->employee?->department?->name ?? 'Unassigned';
            $byDepartment[$departmentName] ??= ['department' => $departmentName, 'gross_pay' => '0.00', 'deduction_total' => '0.00', 'net_pay' => '0.00', 'employee_count' => 0];
            $byDepartment[$departmentName]['gross_pay'] = Money::add($byDepartment[$departmentName]['gross_pay'], (string) $item->gross_pay);
            $byDepartment[$departmentName]['deduction_total'] = Money::add($byDepartment[$departmentName]['deduction_total'], (string) $item->deduction_total);
            $byDepartment[$departmentName]['net_pay'] = Money::add($byDepartment[$departmentName]['net_pay'], (string) $item->net_pay);
            $byDepartment[$departmentName]['employee_count']++;

            return [
                'payroll_item_id' => $item->id,
                'payroll_run_id' => $item->payroll_run_id,
                'reference' => $item->payrollRun?->reference,
                'status' => $item->payrollRun?->status,
                'employee_id' => $item->employee_id,
                'name' => trim(($item->employee?->user?->first_name ?? '').' '.($item->employee?->user?->last_name ?? '')),
                'department' => $departmentName,
                'gross_pay' => $item->gross_pay,
                'deduction_total' => $item->deduction_total,
                'net_pay' => $item->net_pay,
                'overtime_minutes' => $item->overtime_minutes,
            ];
        })->all();

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'gross_pay' => $gross,
                'deduction_total' => $deductions,
                'net_pay' => $net,
                'employee_count' => $items->pluck('employee_id')->unique()->count(),
            ],
            'by_department' => array_values($byDepartment),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null, employee_id?: int|null}  $params
     * @return array<string, mixed>
     */
    public function overtime(array $params = []): array
    {
        [$from, $to] = $this->window($params);

        $query = Attendance::query()
            ->with(['employee.user:id,first_name,last_name'])
            ->where('overtime_minutes', '>', 0)
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->when($params['department_id'] ?? null, function ($query, int $id): void {
                $query->whereHas('employee', fn ($employee) => $employee->where('department_id', $id));
            })
            ->when($params['employee_id'] ?? null, fn ($query, int $id) => $query->where('employee_id', $id));

        $records = $query->orderBy('work_date')->orderBy('id')->get();

        $paid = PayrollItemLine::query()
            ->where('code', 'overtime')
            ->whereHas('payrollItem.payrollRun', function ($query) use ($from, $to): void {
                $query->whereNot('status', PayrollRunStatus::Cancelled)
                    ->whereDate('period_start', '<=', $to)
                    ->whereDate('period_end', '>=', $from);
            })
            ->get();

        $paidTotal = '0.00';
        foreach ($paid as $line) {
            $paidTotal = Money::add($paidTotal, (string) $line->amount);
        }

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'overtime_minutes' => (int) $records->sum('overtime_minutes'),
                'paid_overtime' => $paidTotal,
            ],
            'rows' => $records->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'employee_id' => $attendance->employee_id,
                'name' => trim(($attendance->employee?->user?->first_name ?? '').' '.($attendance->employee?->user?->last_name ?? '')),
                'work_date' => $attendance->work_date?->toDateString(),
                'overtime_minutes' => $attendance->overtime_minutes,
                'overtime_rate_percent' => $attendance->overtime_rate_percent,
            ])->all(),
        ];
    }

    /**
     * @param  array{as_of?: string|null}  $params
     * @return array<string, mixed>
     */
    public function headcount(array $params = []): array
    {
        $asOf = $params['as_of'] ?? now()->toDateString();

        $employees = Employee::query()
            ->with('department:id,name')
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('hired_at')->orWhereDate('hired_at', '<=', $asOf);
            })
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('terminated_at')->orWhereDate('terminated_at', '>', $asOf);
            })
            ->get();

        $byDepartment = [];
        $byStatus = [];

        foreach ($employees as $employee) {
            $department = $employee->department?->name ?? 'Unassigned';
            $byDepartment[$department] = ($byDepartment[$department] ?? 0) + 1;
            $status = $employee->employment_status instanceof EmploymentStatus
                ? $employee->employment_status->value
                : (string) $employee->employment_status;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
        }

        return [
            'as_of' => $asOf,
            'total' => $employees->count(),
            'by_department' => collect($byDepartment)->map(fn (int $count, string $department): array => [
                'department' => $department,
                'count' => $count,
            ])->values()->all(),
            'by_status' => collect($byStatus)->map(fn (int $count, string $status): array => [
                'status' => $status,
                'count' => $count,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $params
     * @return array<string, mixed>
     */
    public function recruitment(array $params = []): array
    {
        [$from, $to] = $this->window($params);

        $applications = JobApplication::query()
            ->with('jobOpening:id,title')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        $byJob = $applications
            ->groupBy('job_opening_id')
            ->map(fn ($group, $openingId): array => [
                'job_opening_id' => (int) $openingId,
                'title' => $group->first()?->jobOpening?->title,
                'count' => $group->count(),
            ])
            ->values()
            ->all();

        $byStage = $applications
            ->groupBy(fn (JobApplication $application): string => $application->status->value)
            ->map(fn ($group, $status): array => [
                'status' => $status,
                'count' => $group->count(),
            ])
            ->values()
            ->all();

        $bySource = $applications
            ->groupBy(fn (JobApplication $application): string => $application->source?->value ?? 'unknown')
            ->map(fn ($group, $source): array => [
                'source' => $source,
                'count' => $group->count(),
            ])
            ->values()
            ->all();

        $hires = $applications->where('status', JobApplicationStatus::Hired);
        $timeToHireDays = $hires
            ->map(function (JobApplication $application): ?int {
                if ($application->applied_at === null || $application->updated_at === null) {
                    return null;
                }

                return (int) $application->applied_at->diffInDays($application->updated_at);
            })
            ->filter()
            ->avg();

        return [
            'from' => $from,
            'to' => $to,
            'open_positions' => JobOpening::query()->where('status', JobOpeningStatus::Open)->count(),
            'applications' => $applications->count(),
            'hires' => $hires->count(),
            'average_time_to_hire_days' => $timeToHireDays !== null ? round((float) $timeToHireDays, 1) : null,
            'by_job' => $byJob,
            'by_stage' => $byStage,
            'by_source' => $bySource,
            'rows' => $byJob,
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $params
     * @return array{0: string, 1: string}
     */
    protected function window(array $params): array
    {
        if (! empty($params['from']) && ! empty($params['to'])) {
            return [(string) $params['from'], (string) $params['to']];
        }

        $period = $this->payrollRuns->periodWindow(
            isset($params['from']) ? Carbon::parse((string) $params['from']) : now(),
        );

        return [$period['period_start'], $period['period_end']];
    }
}
