<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Models\HR\ApplicationStageHistory;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\JobApplication;
use App\Models\HR\JobOpening;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollItem;
use App\Services\Tenant\HR\Reports\HrReportQuery;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Detailed HR operational reports for a date window.
 */
class HrReportService
{
    /**
     * Create a new class instance.
     *
     * @param  HrReportQuery  $queries
     */
    public function __construct(private readonly HrReportQuery $queries) {}

    /**
     * Attendance.
     *
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null, employee_id?: int|null}  $params
     * @return array<string, mixed>
     */
    public function attendance(array $params = []): array
    {
        [$from, $to] = $this->queries->dateWindow($params);

        $rows = $this->queries->employeesWithAttendanceTotals($params, $from, $to)
            ->orderBy('id')
            ->get()
            ->map(function (Employee $employee): array {
                return [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => $this->queries->employeeDisplayName($employee),
                    'department_id' => $employee->department_id,
                    'present' => (int) $employee->present_count,
                    'late' => (int) $employee->late_count,
                    'absent' => (int) $employee->absent_count,
                    'overtime_minutes' => (int) ($employee->overtime_minutes_sum ?? 0),
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
     * Leave.
     *
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null, employee_id?: int|null}  $params
     * @return array<string, mixed>
     */
    public function leave(array $params = []): array
    {
        [$from, $to] = $this->queries->dateWindow($params);

        $requests = $this->queries->leaveRequests($params, $from, $to)
            ->orderByDesc('id')
            ->get();

        $byType = [];

        foreach ($requests as $request) {
            $type = $request->leaveType?->code ?? 'unknown';
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
                'name' => $this->queries->employeeDisplayName($request->employee),
                'type' => $request->leaveType?->code,
                'status' => $request->status,
                'start_date' => $request->start_date?->toDateString(),
                'end_date' => $request->end_date?->toDateString(),
            ])->all(),
        ];
    }

    /**
     * Payroll.
     *
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null}  $params
     * @return array<string, mixed>
     */
    public function payroll(array $params = []): array
    {
        [$from, $to] = $this->queries->dateWindow($params);

        $items = $this->queries->payrollItems($params, $from, $to)
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
                'name' => $this->queries->employeeDisplayName($item->employee),
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
     * Overtime.
     *
     * @param  array{from?: string|null, to?: string|null, department_id?: int|null, employee_id?: int|null}  $params
     * @return array<string, mixed>
     */
    public function overtime(array $params = []): array
    {
        [$from, $to] = $this->queries->dateWindow($params);

        $records = $this->queries->overtimeAttendances($params, $from, $to)
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        $paid = $this->queries->overtimePayrollLines($from, $to)->get();

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
                'name' => $this->queries->employeeDisplayName($attendance->employee),
                'work_date' => $attendance->work_date?->toDateString(),
                'overtime_minutes' => $attendance->overtime_minutes,
                'overtime_rate_percent' => $attendance->overtime_rate_percent,
            ])->all(),
        ];
    }

    /**
     * Headcount.
     *
     * @param  array{as_of?: string|null}  $params
     * @return array<string, mixed>
     */
    public function headcount(array $params = []): array
    {
        $asOf = $params['as_of'] ?? now()->toDateString();

        $employees = $this->queries->headcountEmployees($asOf)->get();

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
     * Recruitment.
     *
     * @param  array{from?: string|null, to?: string|null}  $params
     * @return array<string, mixed>
     */
    public function recruitment(array $params = []): array
    {
        [$from, $to] = $this->queries->dateWindow($params);

        $applications = $this->queries->applications($from, $to)->get();

        $history = $applications->isEmpty()
            ? collect()
            : ApplicationStageHistory::query()
                ->whereIn('job_application_id', $applications->modelKeys())
                ->orderBy('id')
                ->get()
                ->groupBy('job_application_id');

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
            'open_positions' => JobOpening::query()->whereIn('status', [JobOpeningStatus::Published, JobOpeningStatus::Open])->count(),
            'applications' => $applications->count(),
            'hires' => $hires->count(),
            'average_time_to_hire_days' => $timeToHireDays !== null ? round((float) $timeToHireDays, 1) : null,
            'funnel' => $this->recruitmentFunnel($applications, $history),
            'time_in_stage' => $this->timeInStage($applications, $history),
            'by_job' => $byJob,
            'by_stage' => $byStage,
            'by_source' => $bySource,
            'rows' => $byJob,
        ];
    }

    /**
     * Recruitment funnel.
     *
     * @param  Collection<int, JobApplication>  $applications
     * @param  Collection<int, JobApplication>  $applications
     * @param  Collection<int|string, Collection<int, ApplicationStageHistory>>  $history
     * @return list<array{status: string, reached: int, advanced: int}>
     */
    protected function recruitmentFunnel($applications, $history): array
    {
        $pipeline = [
            JobApplicationStatus::Received,
            JobApplicationStatus::Screening,
            JobApplicationStatus::Shortlisted,
            JobApplicationStatus::Interview,
            JobApplicationStatus::Offered,
            JobApplicationStatus::Hired,
        ];

        $reachedByApplication = [];

        foreach ($applications as $application) {
            $reached = collect($history->get($application->id, collect()))
                ->map(fn (ApplicationStageHistory $row): string => $row->to_status->value)
                ->push($application->status->value)
                ->unique()
                ->all();

            $reachedByApplication[$application->id] = $reached;
        }

        $funnel = [];

        foreach ($pipeline as $index => $status) {
            $reached = collect($reachedByApplication)
                ->filter(fn (array $statuses): bool => in_array($status->value, $statuses, true))
                ->count();

            $next = $pipeline[$index + 1] ?? null;
            $advanced = $next === null
                ? $reached
                : collect($reachedByApplication)
                    ->filter(fn (array $statuses): bool => in_array($status->value, $statuses, true)
                        && in_array($next->value, $statuses, true))
                    ->count();

            $funnel[] = [
                'status' => $status->value,
                'reached' => $reached,
                'advanced' => $advanced,
            ];
        }

        return $funnel;
    }

    /**
     * Time in stage.
     *
     * @param  Collection<int, JobApplication>  $applications
     * @param  Collection<int, JobApplication>  $applications
     * @param  Collection<int|string, Collection<int, ApplicationStageHistory>>  $history
     * @return list<array{status: string, average_days: float, samples: int}>
     */
    protected function timeInStage($applications, $history): array
    {
        $durations = [];

        foreach ($applications as $application) {
            /** @var Collection<int, ApplicationStageHistory> $rows */
            $rows = collect($history->get($application->id, collect()))->values();

            if ($rows->isEmpty()) {
                $start = $application->applied_at ?? $application->created_at;

                if ($start !== null) {
                    $durations[$application->status->value][] = max(0, $start->diffInSeconds(now()) / 86400);
                }

                continue;
            }

            foreach ($rows as $index => $row) {
                $start = $row->created_at ?? $application->applied_at ?? $application->created_at;
                $end = $rows->get($index + 1)?->created_at ?? now();

                if ($start === null) {
                    continue;
                }

                $durations[$row->to_status->value][] = max(0, $start->diffInSeconds($end) / 86400);
            }
        }

        $summary = [];

        foreach ($durations as $status => $samples) {
            $summary[] = [
                'status' => $status,
                'average_days' => round(array_sum($samples) / count($samples), 1),
                'samples' => count($samples),
            ];
        }

        return $summary;
    }
}
