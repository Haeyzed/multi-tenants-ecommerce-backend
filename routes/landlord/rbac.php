<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\RBAC\PermissionController;
use App\Http\Controllers\Landlord\RBAC\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('landlord.roles.index');
Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('landlord.roles.store');
Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.show')->whereNumber('role')->name('landlord.roles.show');
Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->whereNumber('role')->name('landlord.roles.update');
Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->whereNumber('role')->name('landlord.roles.destroy');
Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.update')->whereNumber('role')->name('landlord.roles.permissions');

Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('landlord.permissions.index');
Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create')->name('landlord.permissions.store');
Route::get('permissions/{permission}', [PermissionController::class, 'show'])->middleware('permission:permissions.show')->whereNumber('permission')->name('landlord.permissions.show');
Route::match(['put', 'patch'], 'permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.update')->whereNumber('permission')->name('landlord.permissions.update');
Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete')->whereNumber('permission')->name('landlord.permissions.destroy');
