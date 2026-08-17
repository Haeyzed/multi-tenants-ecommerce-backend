<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\RBAC\PermissionController;
use App\Http\Controllers\Tenant\RBAC\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('tenant.roles.index');
Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('tenant.roles.store');
Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.show')->whereNumber('role')->name('tenant.roles.show');
Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->whereNumber('role')->name('tenant.roles.update');
Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->whereNumber('role')->name('tenant.roles.destroy');
Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.update')->whereNumber('role')->name('tenant.roles.permissions');

Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('tenant.permissions.index');
Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create')->name('tenant.permissions.store');
Route::get('permissions/{permission}', [PermissionController::class, 'show'])->middleware('permission:permissions.show')->whereNumber('permission')->name('tenant.permissions.show');
Route::match(['put', 'patch'], 'permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.update')->whereNumber('permission')->name('tenant.permissions.update');
Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete')->whereNumber('permission')->name('tenant.permissions.destroy');
