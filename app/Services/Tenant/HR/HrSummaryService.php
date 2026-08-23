<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\EmploymentStatus;
use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Enums\Tenant\HR\LeaveStatus;
use App\Enums\Tenant\HR\PayrollPeriodStatus;
use App\Enums\Tenant\HR\PayrollRunStatus;
use App\Models\Tenant\HR\Attendance;
use App\Models\Tenant\HR\Candidate;
use App\Models\Tenant\HR\Department;
use App\Models\Tenant\HR\Employee;
use App\Models\Tenant\HR\Interview;
use App\Models\Tenant\HR\JobApplication;
use App\Models\Tenant\HR\JobOffer;
use App\Models\Tenant\HR\JobOpening;
use App\Models\Tenant\HR\LeaveRequest;
use App\Models\Tenant\HR\PayrollPeriod;
use App\Models\Tenant\HR\PayrollRun;
use App\Services\Tenant\HR\Reports\HrReportQuery;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight HR dashboard totals.
 */
class HrSummaryService
{
    /**
     * Create a new class instance.
     *
     * @param  HrReportQuery  $queries
     */
    public function __construct(private readonly HrReportQuery $queries) {}

    /**
     * Summary.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $currentPeriod = $this->queries->periodWindow();

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
            'overtime_minutes_this_period' => $this->queries->attendancesInWindow(
                [],
                $currentPeriod['period_start'],
                $currentPeriod['period_end'],
            )->sum('overtime_minutes'),
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
            'recruitment' => $this->recruitmentSummary(),
        ];
    }

    /**
     * Recruitment summary.
     *
     * @return array<string, int>
     */
    protected function recruitmentSummary(): array
    {
        $empty = [
            'open_jobs' => 0,
            'applications' => 0,
            'candidates' => 0,
            'interviews' => 0,
            'offers' => 0,
            'hires' => 0,
            'rejected_applications' => 0,
        ];

        if (! Schema::hasTable('job_openings') || ! Schema::hasTable('job_applications')) {
            return $empty;
        }

        return [
            'open_jobs' => JobOpening::query()->whereIn('status', [JobOpeningStatus::Published, JobOpeningStatus::Open])->count(),
            'applications' => JobApplication::query()->count(),
            'candidates' => Schema::hasTable('candidates') ? Candidate::query()->count() : 0,
            'interviews' => Schema::hasTable('interviews') ? Interview::query()->count() : 0,
            'offers' => Schema::hasTable('job_offers') ? JobOffer::query()->count() : 0,
            'hires' => JobApplication::query()->where('status', JobApplicationStatus::Hired)->count(),
            'rejected_applications' => JobApplication::query()->where('status', JobApplicationStatus::Rejected)->count(),
        ];
    }
}
