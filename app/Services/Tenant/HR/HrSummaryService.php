<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\PayrollPeriodStatus;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\PayrollRun;

/**
 * Lightweight HR dashboard totals.
 */
class HrSummaryService
{
    public function __construct(private readonly PayrollRunService $payrollRuns) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $currentPeriod = $this->payrollRuns->periodWindow();

        return [
            'employees' => [
                'total' => Employee::query()->count(),
                'active' => Employee::query()->where('employment_status', EmploymentStatus::Active)->count(),
                'on_leave' => Employee::query()->where('employment_status', EmploymentStatus::OnLeave)->count(),
                'terminated' => Employee::query()->where('employment_status', EmploymentStatus::Terminated)->count(),
            ],
            'departments' => [
                'total' => Department::query()->count(),
                'active' => Department::query()->where('is_active', true)->count(),
            ],
            'attendance_today' => Attendance::query()->whereDate('work_date', now()->toDateString())->count(),
            'overtime_minutes_this_period' => Attendance::query()
                ->whereDate('work_date', '>=', $currentPeriod['period_start'])
                ->whereDate('work_date', '<=', $currentPeriod['period_end'])
                ->sum('overtime_minutes'),
            'leave' => [
                'pending' => LeaveRequest::query()->where('status', LeaveStatus::Pending)->count(),
                'approved' => LeaveRequest::query()->where('status', LeaveStatus::Approved)->count(),
            ],
            'payroll' => [
                'draft' => PayrollRun::query()->where('status', PayrollRunStatus::Draft)->count(),
                'pending_approval' => PayrollRun::query()->where('status', PayrollRunStatus::PendingApproval)->count(),
                'processed' => PayrollRun::query()->where('status', PayrollRunStatus::Processed)->count(),
                'paid' => PayrollRun::query()->where('status', PayrollRunStatus::Paid)->count(),
                'current_period' => $currentPeriod,
                'open_periods' => PayrollPeriod::query()->where('status', PayrollPeriodStatus::Open)->count(),
            ],
        ];
    }
}
