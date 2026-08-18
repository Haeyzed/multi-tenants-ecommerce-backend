<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\HR\AttendanceController;
use App\Http\Controllers\Tenant\HR\DepartmentController;
use App\Http\Controllers\Tenant\HR\DesignationController;
use App\Http\Controllers\Tenant\HR\EmployeeController;
use App\Http\Controllers\Tenant\HR\EmployeeSalaryController;
use App\Http\Controllers\Tenant\HR\EmploymentRecordController;
use App\Http\Controllers\Tenant\HR\HrReportController;
use App\Http\Controllers\Tenant\HR\HrSettingsController;
use App\Http\Controllers\Tenant\HR\HrSummaryController;
use App\Http\Controllers\Tenant\HR\LeaveBalanceController;
use App\Http\Controllers\Tenant\HR\LeaveRequestController;
use App\Http\Controllers\Tenant\HR\LeaveTypeController;
use App\Http\Controllers\Tenant\HR\OvertimePolicyController;
use App\Http\Controllers\Tenant\HR\PayrollPeriodController;
use App\Http\Controllers\Tenant\HR\PayrollRunController;
use App\Http\Controllers\Tenant\HR\PublicHolidayController;
use App\Http\Controllers\Tenant\HR\TaxTableController;
use App\Http\Controllers\Tenant\HR\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:hr')->group(function (): void {
    Route::get('hr/settings', [HrSettingsController::class, 'show'])->middleware('permission:hr.settings.view|hr.settings.update|hr.view')->name('tenant.hr.settings.show');
    Route::match(['put', 'patch'], 'hr/settings', [HrSettingsController::class, 'update'])->middleware('permission:hr.settings.update')->name('tenant.hr.settings.update');

    Route::middleware('hr.enabled')->group(function (): void {
        Route::get('hr/summary', [HrSummaryController::class, 'show'])->middleware('permission:hr.view|hr.employees.view|hr.payroll.view')->name('tenant.hr.summary');
        Route::get('hr/reports/attendance', [HrReportController::class, 'attendance'])->middleware('permission:hr.reports.view|hr.view|hr.attendance.view')->name('tenant.hr.reports.attendance');
        Route::get('hr/reports/leave', [HrReportController::class, 'leave'])->middleware('permission:hr.reports.view|hr.view|hr.leave.view')->name('tenant.hr.reports.leave');
        Route::get('hr/reports/payroll', [HrReportController::class, 'payroll'])->middleware('permission:hr.reports.view|hr.view|hr.payroll.view')->name('tenant.hr.reports.payroll');
        Route::get('hr/reports/overtime', [HrReportController::class, 'overtime'])->middleware('permission:hr.reports.view|hr.view|hr.attendance.view|hr.payroll.view')->name('tenant.hr.reports.overtime');
        Route::get('hr/reports/headcount', [HrReportController::class, 'headcount'])->middleware('permission:hr.reports.view|hr.view|hr.employees.view')->name('tenant.hr.reports.headcount');

        Route::get('work-schedules/options', [WorkScheduleController::class, 'options'])->name('tenant.work-schedules.options');
        Route::get('work-schedules', [WorkScheduleController::class, 'index'])->name('tenant.work-schedules.index');
        Route::post('work-schedules', [WorkScheduleController::class, 'store'])->name('tenant.work-schedules.store');
        Route::get('work-schedules/{work_schedule}', [WorkScheduleController::class, 'show'])->whereNumber('work_schedule')->name('tenant.work-schedules.show');
        Route::match(['put', 'patch'], 'work-schedules/{work_schedule}', [WorkScheduleController::class, 'update'])->whereNumber('work_schedule')->name('tenant.work-schedules.update');
        Route::delete('work-schedules/{work_schedule}', [WorkScheduleController::class, 'destroy'])->whereNumber('work_schedule')->name('tenant.work-schedules.destroy');

        Route::get('overtime-policies/options', [OvertimePolicyController::class, 'options'])->name('tenant.overtime-policies.options');
        Route::get('overtime-policies', [OvertimePolicyController::class, 'index'])->name('tenant.overtime-policies.index');
        Route::post('overtime-policies', [OvertimePolicyController::class, 'store'])->name('tenant.overtime-policies.store');
        Route::get('overtime-policies/{overtime_policy}', [OvertimePolicyController::class, 'show'])->whereNumber('overtime_policy')->name('tenant.overtime-policies.show');
        Route::match(['put', 'patch'], 'overtime-policies/{overtime_policy}', [OvertimePolicyController::class, 'update'])->whereNumber('overtime_policy')->name('tenant.overtime-policies.update');
        Route::delete('overtime-policies/{overtime_policy}', [OvertimePolicyController::class, 'destroy'])->whereNumber('overtime_policy')->name('tenant.overtime-policies.destroy');

        Route::get('public-holidays', [PublicHolidayController::class, 'index'])->name('tenant.public-holidays.index');
        Route::post('public-holidays', [PublicHolidayController::class, 'store'])->name('tenant.public-holidays.store');
        Route::get('public-holidays/{public_holiday}', [PublicHolidayController::class, 'show'])->whereNumber('public_holiday')->name('tenant.public-holidays.show');
        Route::match(['put', 'patch'], 'public-holidays/{public_holiday}', [PublicHolidayController::class, 'update'])->whereNumber('public_holiday')->name('tenant.public-holidays.update');
        Route::delete('public-holidays/{public_holiday}', [PublicHolidayController::class, 'destroy'])->whereNumber('public_holiday')->name('tenant.public-holidays.destroy');

        Route::get('tax-tables', [TaxTableController::class, 'index'])->name('tenant.tax-tables.index');
        Route::post('tax-tables', [TaxTableController::class, 'store'])->name('tenant.tax-tables.store');
        Route::get('tax-tables/{tax_table}', [TaxTableController::class, 'show'])->whereNumber('tax_table')->name('tenant.tax-tables.show');
        Route::match(['put', 'patch'], 'tax-tables/{tax_table}', [TaxTableController::class, 'update'])->whereNumber('tax_table')->name('tenant.tax-tables.update');
        Route::delete('tax-tables/{tax_table}', [TaxTableController::class, 'destroy'])->whereNumber('tax_table')->name('tenant.tax-tables.destroy');

        Route::get('departments/options', [DepartmentController::class, 'options'])->middleware('permission:hr.view|hr.departments.view|hr.departments.manage')->name('tenant.departments.options');
        Route::get('departments', [DepartmentController::class, 'index'])->middleware('permission:hr.view|hr.departments.view|hr.departments.manage')->name('tenant.departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:hr.departments.manage')->name('tenant.departments.store');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->middleware('permission:hr.view|hr.departments.view|hr.departments.manage')->whereNumber('department')->name('tenant.departments.show');
        Route::match(['put', 'patch'], 'departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:hr.departments.manage')->whereNumber('department')->name('tenant.departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:hr.departments.manage')->whereNumber('department')->name('tenant.departments.destroy');

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

        Route::get('leave-types/options', [LeaveTypeController::class, 'options'])->name('tenant.leave-types.options');
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('tenant.leave-types.index');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])->middleware('permission:hr.leave.manage')->name('tenant.leave-types.store');
        Route::get('leave-types/{leave_type}', [LeaveTypeController::class, 'show'])->whereNumber('leave_type')->name('tenant.leave-types.show');
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
    });
});
