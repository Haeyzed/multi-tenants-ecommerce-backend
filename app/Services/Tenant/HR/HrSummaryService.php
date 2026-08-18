<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\PayrollRun;

/**
 * Lightweight HR dashboard totals.
 */
class HrSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
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
            'leave' => [
                'pending' => LeaveRequest::query()->where('status', LeaveStatus::Pending)->count(),
                'approved' => LeaveRequest::query()->where('status', LeaveStatus::Approved)->count(),
            ],
            'payroll' => [
                'draft' => PayrollRun::query()->where('status', PayrollRunStatus::Draft)->count(),
                'pending_approval' => PayrollRun::query()->where('status', PayrollRunStatus::PendingApproval)->count(),
                'processed' => PayrollRun::query()->where('status', PayrollRunStatus::Processed)->count(),
                'paid' => PayrollRun::query()->where('status', PayrollRunStatus::Paid)->count(),
            ],
        ];
    }
}
