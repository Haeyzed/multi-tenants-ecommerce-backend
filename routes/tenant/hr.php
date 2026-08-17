<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\HR\AttendanceController;
use App\Http\Controllers\Tenant\HR\DepartmentController;
use App\Http\Controllers\Tenant\HR\DesignationController;
use App\Http\Controllers\Tenant\HR\EmployeeController;
use App\Http\Controllers\Tenant\HR\LeaveRequestController;
use Illuminate\Support\Facades\Route;

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
Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('permission:hr.employees.view|hr.view')->whereNumber('employee')->name('tenant.employees.show');
Route::match(['put', 'patch'], 'employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:hr.employees.update')->whereNumber('employee')->name('tenant.employees.update');
Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:hr.employees.delete')->whereNumber('employee')->name('tenant.employees.destroy');
Route::get('employees/{employee}/documents', [EmployeeController::class, 'documents'])->middleware('permission:hr.employees.view|hr.view|hr.employees.update')->whereNumber('employee')->name('tenant.employees.documents.index');
Route::post('employees/{employee}/documents', [EmployeeController::class, 'storeDocument'])->middleware('permission:hr.employees.update')->whereNumber('employee')->name('tenant.employees.documents.store');
Route::delete('employees/{employee}/documents/{media}', [EmployeeController::class, 'destroyDocument'])->middleware('permission:hr.employees.update')->whereNumber('employee')->whereNumber('media')->name('tenant.employees.documents.destroy');

Route::post('attendances/clock-in', [AttendanceController::class, 'clockIn'])->name('tenant.attendances.clock-in');
Route::post('attendances/clock-out', [AttendanceController::class, 'clockOut'])->name('tenant.attendances.clock-out');
Route::get('attendances', [AttendanceController::class, 'index'])->name('tenant.attendances.index');
Route::post('attendances', [AttendanceController::class, 'store'])->middleware('permission:hr.attendance.manage')->name('tenant.attendances.store');
Route::get('attendances/{attendance}', [AttendanceController::class, 'show'])->whereNumber('attendance')->name('tenant.attendances.show');
Route::match(['put', 'patch'], 'attendances/{attendance}', [AttendanceController::class, 'update'])->middleware('permission:hr.attendance.manage')->whereNumber('attendance')->name('tenant.attendances.update');
Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->middleware('permission:hr.attendance.manage')->whereNumber('attendance')->name('tenant.attendances.destroy');

Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('tenant.leave-requests.index');
Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('tenant.leave-requests.store');
Route::get('leave-requests/{leave_request}', [LeaveRequestController::class, 'show'])->whereNumber('leave_request')->name('tenant.leave-requests.show');
Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve'])->middleware('permission:hr.leave.manage')->whereNumber('leave_request')->name('tenant.leave-requests.approve');
Route::post('leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject'])->middleware('permission:hr.leave.manage')->whereNumber('leave_request')->name('tenant.leave-requests.reject');
Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel'])->whereNumber('leave_request')->name('tenant.leave-requests.cancel');
