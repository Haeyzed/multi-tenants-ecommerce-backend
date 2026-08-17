<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Driver\DriverController;
use Illuminate\Support\Facades\Route;

Route::get('drivers', [DriverController::class, 'index'])->middleware('permission:drivers.view')->name('tenant.drivers.index');
Route::post('drivers', [DriverController::class, 'store'])->middleware('permission:drivers.create')->name('tenant.drivers.store');
Route::get('drivers/{driver}/deliveries', [DriverController::class, 'deliveries'])->middleware('permission:drivers.view')->whereNumber('driver')->name('tenant.drivers.deliveries');
Route::get('drivers/{driver}/stats', [DriverController::class, 'stats'])->middleware('permission:drivers.view')->whereNumber('driver')->name('tenant.drivers.stats');
Route::get('drivers/{driver}', [DriverController::class, 'show'])->middleware('permission:drivers.show')->whereNumber('driver')->name('tenant.drivers.show');
Route::match(['put', 'patch'], 'drivers/{driver}', [DriverController::class, 'update'])->middleware('permission:drivers.update')->whereNumber('driver')->name('tenant.drivers.update');
Route::delete('drivers/{driver}', [DriverController::class, 'destroy'])->middleware('permission:drivers.delete')->whereNumber('driver')->name('tenant.drivers.destroy');
