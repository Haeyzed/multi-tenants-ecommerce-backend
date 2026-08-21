<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\HR\AttendanceController;
use App\Http\Controllers\Tenant\HR\CandidateController;
use App\Http\Controllers\Tenant\HR\DepartmentController;
use App\Http\Controllers\Tenant\HR\DesignationController;
use App\Http\Controllers\Tenant\HR\EmployeeController;
use App\Http\Controllers\Tenant\HR\EmployeeSalaryController;
use App\Http\Controllers\Tenant\HR\EmploymentRecordController;
use App\Http\Controllers\Tenant\HR\HrReportController;
use App\Http\Controllers\Tenant\HR\HrSettingsController;
use App\Http\Controllers\Tenant\HR\HrSummaryController;
use App\Http\Controllers\Tenant\HR\InterviewController;
use App\Http\Controllers\Tenant\HR\InterviewMeetingController;
use App\Http\Controllers\Tenant\HR\InterviewMeetingProviderController;
use App\Http\Controllers\Tenant\HR\JobApplicationController;
use App\Http\Controllers\Tenant\HR\JobOfferController;
use App\Http\Controllers\Tenant\HR\JobOpeningController;
use App\Http\Controllers\Tenant\HR\LeaveBalanceController;
use App\Http\Controllers\Tenant\HR\LeaveRequestController;
use App\Http\Controllers\Tenant\HR\LeaveTypeController;
use App\Http\Controllers\Tenant\HR\OvertimePolicyController;
use App\Http\Controllers\Tenant\HR\PayrollPeriodController;
use App\Http\Controllers\Tenant\HR\PayrollRunController;
use App\Http\Controllers\Tenant\HR\PerformanceCycleController;
use App\Http\Controllers\Tenant\HR\PerformanceReviewController;
use App\Http\Controllers\Tenant\HR\PublicHolidayController;
use App\Http\Controllers\Tenant\HR\RecruitmentStageController;
use App\Http\Controllers\Tenant\HR\TaxTableController;
use App\Http\Controllers\Tenant\HR\WorkLocationController;
use App\Http\Controllers\Tenant\HR\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:hr')->group(function (): void {
    Route::get('hr/settings', [HrSettingsController::class, 'show'])->middleware('permission:hr.settings.view|hr.settings.update|hr.view')->name('tenant.hr.settings.show');
    Route::match(['put', 'patch'], 'hr/settings', [HrSettingsController::class, 'update'])->middleware('permission:hr.settings.update')->name('tenant.hr.settings.update');

    Route::get('hr/interview-providers', [InterviewMeetingProviderController::class, 'index'])->middleware('permission:hr.settings.view|hr.settings.update|hr.view')->name('tenant.hr.interview-providers.index');
    Route::match(['put', 'patch'], 'hr/interview-providers/{provider}', [InterviewMeetingProviderController::class, 'update'])->middleware('permission:hr.settings.update')->name('tenant.hr.interview-providers.update');
    Route::post('hr/interview-providers/{provider}/test', [InterviewMeetingProviderController::class, 'test'])->middleware('permission:hr.settings.update')->name('tenant.hr.interview-providers.test');

    Route::middleware('hr.enabled')->group(function (): void {
        Route::get('hr/summary', [HrSummaryController::class, 'show'])->middleware('permission:hr.view|hr.employees.view|hr.payroll.view')->name('tenant.hr.summary');
        Route::get('hr/reports/attendance', [HrReportController::class, 'attendance'])->middleware('permission:hr.reports.view|hr.view|hr.attendance.view')->name('tenant.hr.reports.attendance');
        Route::get('hr/reports/leave', [HrReportController::class, 'leave'])->middleware('permission:hr.reports.view|hr.view|hr.leave.view')->name('tenant.hr.reports.leave');
        Route::get('hr/reports/payroll', [HrReportController::class, 'payroll'])->middleware('permission:hr.reports.view|hr.view|hr.payroll.view')->name('tenant.hr.reports.payroll');
        Route::get('hr/reports/overtime', [HrReportController::class, 'overtime'])->middleware('permission:hr.reports.view|hr.view|hr.attendance.view|hr.payroll.view')->name('tenant.hr.reports.overtime');
        Route::get('hr/reports/headcount', [HrReportController::class, 'headcount'])->middleware('permission:hr.reports.view|hr.view|hr.employees.view')->name('tenant.hr.reports.headcount');
        Route::get('hr/reports/statutory', [HrReportController::class, 'statutory'])->middleware('permission:hr.reports.view|hr.view|hr.payroll.view')->name('tenant.hr.reports.statutory');
        Route::get('hr/reports/recruitment', [HrReportController::class, 'recruitment'])->middleware('permission:hr.reports.view|hr.view|hr.recruitment.view|hr.recruitment.manage')->name('tenant.hr.reports.recruitment');

        Route::get('work-schedules/options', [WorkScheduleController::class, 'options'])->middleware('permission:hr.view|hr.attendance.manage|hr.attendance.view')->name('tenant.work-schedules.options');
        Route::get('work-schedules', [WorkScheduleController::class, 'index'])->middleware('permission:hr.view|hr.attendance.manage|hr.attendance.view')->name('tenant.work-schedules.index');
        Route::post('work-schedules', [WorkScheduleController::class, 'store'])->middleware('permission:hr.attendance.manage')->name('tenant.work-schedules.store');
        Route::get('work-schedules/{work_schedule}', [WorkScheduleController::class, 'show'])->middleware('permission:hr.view|hr.attendance.manage|hr.attendance.view')->whereNumber('work_schedule')->name('tenant.work-schedules.show');
        Route::match(['put', 'patch'], 'work-schedules/{work_schedule}', [WorkScheduleController::class, 'update'])->middleware('permission:hr.attendance.manage')->whereNumber('work_schedule')->name('tenant.work-schedules.update');
        Route::delete('work-schedules/{work_schedule}', [WorkScheduleController::class, 'destroy'])->middleware('permission:hr.attendance.manage')->whereNumber('work_schedule')->name('tenant.work-schedules.destroy');

        Route::get('overtime-policies/options', [OvertimePolicyController::class, 'options'])->middleware('permission:hr.view|hr.attendance.manage|hr.attendance.view|hr.payroll.manage')->name('tenant.overtime-policies.options');
        Route::get('overtime-policies', [OvertimePolicyController::class, 'index'])->middleware('permission:hr.view|hr.attendance.manage|hr.attendance.view|hr.payroll.manage')->name('tenant.overtime-policies.index');
        Route::post('overtime-policies', [OvertimePolicyController::class, 'store'])->middleware('permission:hr.attendance.manage|hr.payroll.manage')->name('tenant.overtime-policies.store');
        Route::get('overtime-policies/{overtime_policy}', [OvertimePolicyController::class, 'show'])->middleware('permission:hr.view|hr.attendance.manage|hr.attendance.view|hr.payroll.manage')->whereNumber('overtime_policy')->name('tenant.overtime-policies.show');
        Route::match(['put', 'patch'], 'overtime-policies/{overtime_policy}', [OvertimePolicyController::class, 'update'])->middleware('permission:hr.attendance.manage|hr.payroll.manage')->whereNumber('overtime_policy')->name('tenant.overtime-policies.update');
        Route::delete('overtime-policies/{overtime_policy}', [OvertimePolicyController::class, 'destroy'])->middleware('permission:hr.attendance.manage|hr.payroll.manage')->whereNumber('overtime_policy')->name('tenant.overtime-policies.destroy');

        Route::get('public-holidays', [PublicHolidayController::class, 'index'])->middleware('permission:hr.view|hr.attendance.manage|hr.attendance.view|hr.leave.manage|hr.leave.view')->name('tenant.public-holidays.index');
        Route::post('public-holidays', [PublicHolidayController::class, 'store'])->middleware('permission:hr.attendance.manage|hr.leave.manage')->name('tenant.public-holidays.store');
        Route::get('public-holidays/{public_holiday}', [PublicHolidayController::class, 'show'])->middleware('permission:hr.view|hr.attendance.manage|hr.attendance.view|hr.leave.manage|hr.leave.view')->whereNumber('public_holiday')->name('tenant.public-holidays.show');
        Route::match(['put', 'patch'], 'public-holidays/{public_holiday}', [PublicHolidayController::class, 'update'])->middleware('permission:hr.attendance.manage|hr.leave.manage')->whereNumber('public_holiday')->name('tenant.public-holidays.update');
        Route::delete('public-holidays/{public_holiday}', [PublicHolidayController::class, 'destroy'])->middleware('permission:hr.attendance.manage|hr.leave.manage')->whereNumber('public_holiday')->name('tenant.public-holidays.destroy');

        Route::get('tax-tables', [TaxTableController::class, 'index'])->middleware('permission:hr.payroll.view|hr.payroll.manage|hr.view')->name('tenant.tax-tables.index');
        Route::post('tax-tables', [TaxTableController::class, 'store'])->middleware('permission:hr.payroll.manage')->name('tenant.tax-tables.store');
        Route::get('tax-tables/{tax_table}', [TaxTableController::class, 'show'])->middleware('permission:hr.payroll.view|hr.payroll.manage|hr.view')->whereNumber('tax_table')->name('tenant.tax-tables.show');
        Route::match(['put', 'patch'], 'tax-tables/{tax_table}', [TaxTableController::class, 'update'])->middleware('permission:hr.payroll.manage')->whereNumber('tax_table')->name('tenant.tax-tables.update');
        Route::delete('tax-tables/{tax_table}', [TaxTableController::class, 'destroy'])->middleware('permission:hr.payroll.manage')->whereNumber('tax_table')->name('tenant.tax-tables.destroy');

        Route::get('departments/options', [DepartmentController::class, 'options'])->middleware('permission:hr.view|hr.departments.view|hr.departments.manage')->name('tenant.departments.options');
        Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:hr.view|hr.departments.view|hr.departments.manage')->name('tenant.departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:hr.departments.manage')->name('tenant.departments.store');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->middleware('permission:hr.view|hr.departments.view|hr.departments.manage')->whereNumber('department')->name('tenant.departments.show');
        Route::match(['put', 'patch'], 'departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:hr.departments.manage')->whereNumber('department')->name('tenant.departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:hr.departments.manage')->whereNumber('department')->name('tenant.departments.destroy');

        Route::get('work-locations/options', [WorkLocationController::class, 'options'])->middleware('permission:hr.view|hr.work_locations.view|hr.work_locations.manage')->name('tenant.work-locations.options');
        Route::get('work-locations', [WorkLocationController::class, 'index'])->middleware('permission:hr.view|hr.work_locations.view|hr.work_locations.manage')->name('tenant.work-locations.index');
        Route::post('work-locations', [WorkLocationController::class, 'store'])->middleware('permission:hr.work_locations.manage')->name('tenant.work-locations.store');
        Route::get('work-locations/{work_location}', [WorkLocationController::class, 'show'])->middleware('permission:hr.view|hr.work_locations.view|hr.work_locations.manage')->whereNumber('work_location')->name('tenant.work-locations.show');
        Route::match(['put', 'patch'], 'work-locations/{work_location}', [WorkLocationController::class, 'update'])->middleware('permission:hr.work_locations.manage')->whereNumber('work_location')->name('tenant.work-locations.update');
        Route::delete('work-locations/{work_location}', [WorkLocationController::class, 'destroy'])->middleware('permission:hr.work_locations.manage')->whereNumber('work_location')->name('tenant.work-locations.destroy');

        Route::get('designations/options', [DesignationController::class, 'options'])->middleware('permission:hr.view|hr.designations.view|hr.designations.manage')->name('tenant.designations.options');
        Route::get('designations', [DesignationController::class, 'index'])->middleware('permission:hr.view|hr.designations.view|hr.designations.manage')->name('tenant.designations.index');
        Route::post('designations', [DesignationController::class, 'store'])->middleware('permission:hr.designations.manage')->name('tenant.designations.store');
        Route::get('designations/{designation}', [DesignationController::class, 'show'])->middleware('permission:hr.view|hr.designations.view|hr.designations.manage')->whereNumber('designation')->name('tenant.designations.show');
        Route::match(['put', 'patch'], 'designations/{designation}', [DesignationController::class, 'update'])->middleware('permission:hr.designations.manage')->whereNumber('designation')->name('tenant.designations.update');
        Route::delete('designations/{designation}', [DesignationController::class, 'destroy'])->middleware('permission:hr.designations.manage')->whereNumber('designation')->name('tenant.designations.destroy');

        Route::get('employees', [EmployeeController::class, 'index'])->middleware('permission:hr.employees.view|hr.view')->name('tenant.employees.index');
        Route::post('employees', [EmployeeController::class, 'store'])->middleware('permission:hr.employees.create')->name('tenant.employees.store');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('tenant.employees.show');
        Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:hr.employees.update')->whereNumber('employee')->name('tenant.employees.update');
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:hr.employees.delete')->whereNumber('employee')->name('tenant.employees.destroy');
        Route::get('employees/{employee}/documents', [EmployeeController::class, 'documents'])->whereNumber('employee')->name('tenant.employees.documents.index');
        Route::post('employees/{employee}/documents', [EmployeeController::class, 'storeDocument'])->middleware('permission:hr.employees.update')->whereNumber('employee')->name('tenant.employees.documents.store');
        Route::delete('employees/{employee}/documents/{media}', [EmployeeController::class, 'destroyDocument'])->middleware('permission:hr.employees.update')->whereNumber('employee')->whereNumber('media')->name('tenant.employees.documents.destroy');
        Route::get('employees/{employee}/leave-balances', [LeaveBalanceController::class, 'index'])->whereNumber('employee')->name('tenant.employees.leave-balances.index');
        Route::get('employees/{employee}/employment-records', [EmploymentRecordController::class, 'index'])->whereNumber('employee')->name('tenant.employees.employment-records.index');
        Route::get('employees/{employee}/payslips', [PayrollRunController::class, 'employeeItems'])->whereNumber('employee')->name('tenant.employees.payslips.index');
        Route::get('employees/{employee}/salary', [EmployeeSalaryController::class, 'show'])->whereNumber('employee')->name('tenant.employees.salary.show');
        Route::get('employees/{employee}/salary/revisions', [EmployeeSalaryController::class, 'history'])->whereNumber('employee')->name('tenant.employees.salary.revisions');
        Route::match(['put', 'patch'], 'employees/{employee}/salary', [EmployeeSalaryController::class, 'upsert'])->middleware('permission:hr.payroll.manage')->whereNumber('employee')->name('tenant.employees.salary.upsert');

        Route::post('attendances/clock-in', [AttendanceController::class, 'clockIn'])->name('tenant.attendances.clock-in');
        Route::post('attendances/clock-out', [AttendanceController::class, 'clockOut'])->name('tenant.attendances.clock-out');
        Route::get('attendances', [AttendanceController::class, 'index'])->name('tenant.attendances.index');
        Route::post('attendances', [AttendanceController::class, 'store'])->middleware('permission:hr.attendance.manage')->name('tenant.attendances.store');
        Route::get('attendances/{attendance}', [AttendanceController::class, 'show'])->whereNumber('attendance')->name('tenant.attendances.show');
        Route::match(['put', 'patch'], 'attendances/{attendance}', [AttendanceController::class, 'update'])->middleware('permission:hr.attendance.manage')->whereNumber('attendance')->name('tenant.attendances.update');
        Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->middleware('permission:hr.attendance.manage')->whereNumber('attendance')->name('tenant.attendances.destroy');

        Route::get('leave-types/options', [LeaveTypeController::class, 'options'])->middleware('permission:hr.leave.view|hr.leave.manage|hr.view')->name('tenant.leave-types.options');
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->middleware('permission:hr.leave.view|hr.leave.manage|hr.view')->name('tenant.leave-types.index');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])->middleware('permission:hr.leave.manage')->name('tenant.leave-types.store');
        Route::get('leave-types/{leave_type}', [LeaveTypeController::class, 'show'])->middleware('permission:hr.leave.view|hr.leave.manage|hr.view')->whereNumber('leave_type')->name('tenant.leave-types.show');
        Route::match(['put', 'patch'], 'leave-types/{leave_type}', [LeaveTypeController::class, 'update'])->middleware('permission:hr.leave.manage')->whereNumber('leave_type')->name('tenant.leave-types.update');
        Route::delete('leave-types/{leave_type}', [LeaveTypeController::class, 'destroy'])->middleware('permission:hr.leave.manage')->whereNumber('leave_type')->name('tenant.leave-types.destroy');

        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('tenant.leave-requests.index');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('tenant.leave-requests.store');
        Route::get('leave-requests/{leave_request}', [LeaveRequestController::class, 'show'])->whereNumber('leave_request')->name('tenant.leave-requests.show');
        Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve'])->middleware('permission:hr.leave.manage|hr.leave.approve')->whereNumber('leave_request')->name('tenant.leave-requests.approve');
        Route::post('leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject'])->middleware('permission:hr.leave.manage|hr.leave.approve')->whereNumber('leave_request')->name('tenant.leave-requests.reject');
        Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel'])->whereNumber('leave_request')->name('tenant.leave-requests.cancel');

        Route::get('payroll-periods', [PayrollPeriodController::class, 'index'])->middleware('permission:hr.payroll.view|hr.payroll.manage|hr.view')->name('tenant.payroll-periods.index');
        Route::get('payroll-periods/current', [PayrollPeriodController::class, 'current'])->middleware('permission:hr.payroll.view|hr.payroll.manage|hr.view')->name('tenant.payroll-periods.current');
        Route::get('payroll-runs', [PayrollRunController::class, 'index'])->middleware('permission:hr.payroll.view|hr.payroll.manage|hr.view')->name('tenant.payroll-runs.index');
        Route::post('payroll-runs', [PayrollRunController::class, 'store'])->middleware('permission:hr.payroll.manage')->name('tenant.payroll-runs.store');
        Route::get('payroll-runs/{payroll_run}', [PayrollRunController::class, 'show'])->middleware('permission:hr.payroll.view|hr.payroll.manage|hr.view')->whereNumber('payroll_run')->name('tenant.payroll-runs.show');
        Route::post('payroll-runs/{payroll_run}/generate', [PayrollRunController::class, 'generate'])->middleware('permission:hr.payroll.manage')->whereNumber('payroll_run')->name('tenant.payroll-runs.generate');
        Route::post('payroll-runs/{payroll_run}/process', [PayrollRunController::class, 'process'])->middleware('permission:hr.payroll.manage')->whereNumber('payroll_run')->name('tenant.payroll-runs.process');
        Route::post('payroll-runs/{payroll_run}/approve', [PayrollRunController::class, 'approve'])->middleware('permission:hr.payroll.manage|hr.payroll.approve')->whereNumber('payroll_run')->name('tenant.payroll-runs.approve');
        Route::post('payroll-runs/{payroll_run}/pay', [PayrollRunController::class, 'pay'])->middleware('permission:hr.payroll.manage')->whereNumber('payroll_run')->name('tenant.payroll-runs.pay');
        Route::post('payroll-runs/{payroll_run}/cancel', [PayrollRunController::class, 'cancel'])->middleware('permission:hr.payroll.manage')->whereNumber('payroll_run')->name('tenant.payroll-runs.cancel');
        Route::get('payroll-runs/{payroll_run}/items/{payroll_item}', [PayrollRunController::class, 'showItem'])->whereNumber('payroll_run')->whereNumber('payroll_item')->name('tenant.payroll-runs.items.show');
        Route::get('payroll-runs/{payroll_run}/items/{payroll_item}/pdf', [PayrollRunController::class, 'downloadItem'])->whereNumber('payroll_run')->whereNumber('payroll_item')->name('tenant.payroll-runs.items.pdf');
        Route::get('payroll-runs/{payroll_run}/payment-register', [PayrollRunController::class, 'paymentRegister'])->middleware('permission:hr.payroll.view|hr.payroll.manage|hr.view')->whereNumber('payroll_run')->name('tenant.payroll-runs.payment-register');
        Route::get('payroll-runs/{payroll_run}/nibss', [PayrollRunController::class, 'nibssFile'])->middleware('permission:hr.payroll.view|hr.payroll.manage|hr.view')->whereNumber('payroll_run')->name('tenant.payroll-runs.nibss.file');
        Route::post('payroll-runs/{payroll_run}/nibss', [PayrollRunController::class, 'nibssSubmit'])->middleware('permission:hr.payroll.manage')->whereNumber('payroll_run')->name('tenant.payroll-runs.nibss.submit');

        Route::get('job-openings', [JobOpeningController::class, 'index'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->name('tenant.job-openings.index');
        Route::post('job-openings', [JobOpeningController::class, 'store'])->middleware('permission:hr.recruitment.manage')->name('tenant.job-openings.store');
        Route::get('job-openings/{job_opening}', [JobOpeningController::class, 'show'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->whereNumber('job_opening')->name('tenant.job-openings.show');
        Route::match(['put', 'patch'], 'job-openings/{job_opening}', [JobOpeningController::class, 'update'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_opening')->name('tenant.job-openings.update');
        Route::delete('job-openings/{job_opening}', [JobOpeningController::class, 'destroy'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_opening')->name('tenant.job-openings.destroy');
        Route::post('job-openings/{job_opening}/publish', [JobOpeningController::class, 'publish'])->middleware('permission:hr.recruitment.publish|hr.recruitment.manage')->whereNumber('job_opening')->name('tenant.job-openings.publish');
        Route::post('job-openings/{job_opening}/pause', [JobOpeningController::class, 'pause'])->middleware('permission:hr.recruitment.publish|hr.recruitment.manage')->whereNumber('job_opening')->name('tenant.job-openings.pause');
        Route::post('job-openings/{job_opening}/close', [JobOpeningController::class, 'close'])->middleware('permission:hr.recruitment.publish|hr.recruitment.manage')->whereNumber('job_opening')->name('tenant.job-openings.close');
        Route::post('job-openings/{job_opening}/cancel', [JobOpeningController::class, 'cancel'])->middleware('permission:hr.recruitment.publish|hr.recruitment.manage')->whereNumber('job_opening')->name('tenant.job-openings.cancel');
        Route::post('job-openings/{job_opening}/image', [JobOpeningController::class, 'image'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_opening')->name('tenant.job-openings.image');

        Route::get('candidates', [CandidateController::class, 'index'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->name('tenant.candidates.index');
        Route::post('candidates', [CandidateController::class, 'store'])->middleware('permission:hr.recruitment.manage')->name('tenant.candidates.store');
        Route::get('candidates/{candidate}', [CandidateController::class, 'show'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->whereNumber('candidate')->name('tenant.candidates.show');
        Route::get('candidates/{candidate}/activities', [CandidateController::class, 'activities'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->whereNumber('candidate')->name('tenant.candidates.activities');
        Route::match(['put', 'patch'], 'candidates/{candidate}', [CandidateController::class, 'update'])->middleware('permission:hr.recruitment.manage')->whereNumber('candidate')->name('tenant.candidates.update');
        Route::post('candidates/{candidate}/resume', [CandidateController::class, 'resume'])->middleware('permission:hr.recruitment.manage')->whereNumber('candidate')->name('tenant.candidates.resume');
        Route::delete('candidates/{candidate}', [CandidateController::class, 'destroy'])->middleware('permission:hr.recruitment.manage')->whereNumber('candidate')->name('tenant.candidates.destroy');

        Route::get('recruitment-stages', [RecruitmentStageController::class, 'index'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->name('tenant.recruitment-stages.index');
        Route::post('recruitment-stages', [RecruitmentStageController::class, 'store'])->middleware('permission:hr.recruitment.manage')->name('tenant.recruitment-stages.store');
        Route::get('recruitment-stages/{recruitment_stage}', [RecruitmentStageController::class, 'show'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->whereNumber('recruitment_stage')->name('tenant.recruitment-stages.show');
        Route::match(['put', 'patch'], 'recruitment-stages/{recruitment_stage}', [RecruitmentStageController::class, 'update'])->middleware('permission:hr.recruitment.manage')->whereNumber('recruitment_stage')->name('tenant.recruitment-stages.update');
        Route::delete('recruitment-stages/{recruitment_stage}', [RecruitmentStageController::class, 'destroy'])->middleware('permission:hr.recruitment.manage')->whereNumber('recruitment_stage')->name('tenant.recruitment-stages.destroy');

        Route::get('job-applications', [JobApplicationController::class, 'index'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->name('tenant.job-applications.index');
        Route::post('job-applications', [JobApplicationController::class, 'store'])->middleware('permission:hr.recruitment.manage')->name('tenant.job-applications.store');
        Route::get('job-applications/{job_application}', [JobApplicationController::class, 'show'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->whereNumber('job_application')->name('tenant.job-applications.show');
        Route::get('job-applications/{job_application}/activities', [JobApplicationController::class, 'activities'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->whereNumber('job_application')->name('tenant.job-applications.activities');
        Route::match(['put', 'patch'], 'job-applications/{job_application}', [JobApplicationController::class, 'update'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_application')->name('tenant.job-applications.update');
        Route::post('job-applications/{job_application}/stage', [JobApplicationController::class, 'moveStage'])->middleware('permission:hr.recruitment.stage|hr.recruitment.manage')->whereNumber('job_application')->name('tenant.job-applications.stage');
        Route::post('job-applications/{job_application}/hire', [JobApplicationController::class, 'hire'])->middleware('permission:hr.recruitment.hire|hr.recruitment.manage')->whereNumber('job_application')->name('tenant.job-applications.hire');
        Route::delete('job-applications/{job_application}', [JobApplicationController::class, 'destroy'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_application')->name('tenant.job-applications.destroy');

        Route::get('interviews', [InterviewController::class, 'index'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.recruitment.feedback|hr.view')->name('tenant.interviews.index');
        Route::post('interviews', [InterviewController::class, 'store'])->middleware('permission:hr.recruitment.manage')->name('tenant.interviews.store');
        Route::get('interviews/{interview}', [InterviewController::class, 'show'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->whereNumber('interview')->name('tenant.interviews.show');
        Route::match(['put', 'patch'], 'interviews/{interview}', [InterviewController::class, 'update'])->middleware('permission:hr.recruitment.manage')->whereNumber('interview')->name('tenant.interviews.update');
        Route::post('interviews/{interview}/complete', [InterviewController::class, 'complete'])->middleware('permission:hr.recruitment.manage')->whereNumber('interview')->name('tenant.interviews.complete');
        Route::post('interviews/{interview}/cancel', [InterviewController::class, 'cancel'])->middleware('permission:hr.recruitment.manage')->whereNumber('interview')->name('tenant.interviews.cancel');
        Route::post('interviews/{interview}/feedback', [InterviewController::class, 'feedback'])->middleware('permission:hr.recruitment.feedback|hr.recruitment.manage')->whereNumber('interview')->name('tenant.interviews.feedback');
        Route::delete('interviews/{interview}', [InterviewController::class, 'destroy'])->middleware('permission:hr.recruitment.manage')->whereNumber('interview')->name('tenant.interviews.destroy');
        Route::post('interviews/{interview}/meeting', [InterviewMeetingController::class, 'store'])->middleware('permission:hr.recruitment.manage')->whereNumber('interview')->name('tenant.interviews.meeting.store');
        Route::match(['put', 'patch'], 'interviews/{interview}/meeting', [InterviewMeetingController::class, 'update'])->middleware('permission:hr.recruitment.manage')->whereNumber('interview')->name('tenant.interviews.meeting.update');
        Route::delete('interviews/{interview}/meeting', [InterviewMeetingController::class, 'destroy'])->middleware('permission:hr.recruitment.manage')->whereNumber('interview')->name('tenant.interviews.meeting.destroy');

        Route::post('job-offers', [JobOfferController::class, 'store'])->middleware('permission:hr.recruitment.manage')->name('tenant.job-offers.store');
        Route::get('job-offers/{job_offer}', [JobOfferController::class, 'show'])->middleware('permission:hr.recruitment.view|hr.recruitment.manage|hr.view')->whereNumber('job_offer')->name('tenant.job-offers.show');
        Route::match(['put', 'patch'], 'job-offers/{job_offer}', [JobOfferController::class, 'update'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_offer')->name('tenant.job-offers.update');
        Route::post('job-offers/{job_offer}/approve', [JobOfferController::class, 'approve'])->middleware('permission:hr.recruitment.offers.approve|hr.recruitment.manage')->whereNumber('job_offer')->name('tenant.job-offers.approve');
        Route::post('job-offers/{job_offer}/send', [JobOfferController::class, 'send'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_offer')->name('tenant.job-offers.send');
        Route::post('job-offers/{job_offer}/accept', [JobOfferController::class, 'accept'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_offer')->name('tenant.job-offers.accept');
        Route::post('job-offers/{job_offer}/reject', [JobOfferController::class, 'reject'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_offer')->name('tenant.job-offers.reject');
        Route::post('job-offers/{job_offer}/withdraw', [JobOfferController::class, 'withdraw'])->middleware('permission:hr.recruitment.manage')->whereNumber('job_offer')->name('tenant.job-offers.withdraw');

        Route::get('performance-cycles', [PerformanceCycleController::class, 'index'])->middleware('permission:hr.performance.view|hr.performance.manage|hr.view')->name('tenant.performance-cycles.index');
        Route::post('performance-cycles', [PerformanceCycleController::class, 'store'])->middleware('permission:hr.performance.manage')->name('tenant.performance-cycles.store');
        Route::get('performance-cycles/{performance_cycle}', [PerformanceCycleController::class, 'show'])->middleware('permission:hr.performance.view|hr.performance.manage|hr.view')->whereNumber('performance_cycle')->name('tenant.performance-cycles.show');
        Route::match(['put', 'patch'], 'performance-cycles/{performance_cycle}', [PerformanceCycleController::class, 'update'])->middleware('permission:hr.performance.manage')->whereNumber('performance_cycle')->name('tenant.performance-cycles.update');
        Route::delete('performance-cycles/{performance_cycle}', [PerformanceCycleController::class, 'destroy'])->middleware('permission:hr.performance.manage')->whereNumber('performance_cycle')->name('tenant.performance-cycles.destroy');

        Route::get('performance-reviews', [PerformanceReviewController::class, 'index'])->middleware('permission:hr.performance.view|hr.performance.manage|hr.view|hr.employees.view')->name('tenant.performance-reviews.index');
        Route::post('performance-reviews', [PerformanceReviewController::class, 'store'])->middleware('permission:hr.performance.manage')->name('tenant.performance-reviews.store');
        Route::get('performance-reviews/{performance_review}', [PerformanceReviewController::class, 'show'])->whereNumber('performance_review')->name('tenant.performance-reviews.show');
        Route::match(['put', 'patch'], 'performance-reviews/{performance_review}', [PerformanceReviewController::class, 'update'])->middleware('permission:hr.performance.manage')->whereNumber('performance_review')->name('tenant.performance-reviews.update');
        Route::delete('performance-reviews/{performance_review}', [PerformanceReviewController::class, 'destroy'])->middleware('permission:hr.performance.manage')->whereNumber('performance_review')->name('tenant.performance-reviews.destroy');
    });
});
