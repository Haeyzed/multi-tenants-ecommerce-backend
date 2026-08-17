<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\User\UserController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('tenant.users.index');
Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('tenant.users.store');
Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.show')->whereNumber('user')->name('tenant.users.show');
Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->whereNumber('user')->name('tenant.users.update');
Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->whereNumber('user')->name('tenant.users.destroy');
Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->middleware('permission:users.update')->whereNumber('user')->name('tenant.users.roles');
Route::put('users/{user}/permissions', [UserController::class, 'syncPermissions'])->middleware('permission:users.update')->whereNumber('user')->name('tenant.users.permissions');
