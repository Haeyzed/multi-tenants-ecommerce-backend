<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Customer\CustomerController;
use App\Http\Controllers\Tenant\Customer\CustomerGroupController;
use App\Http\Controllers\Tenant\Customer\CustomerSegmentController;
use Illuminate\Support\Facades\Route;

Route::get('segments', [CustomerSegmentController::class, 'index'])->middleware('permission:segments.view')->name('tenant.segments.index');
Route::post('segments', [CustomerSegmentController::class, 'store'])->middleware('permission:segments.manage')->name('tenant.segments.store');
Route::get('segments/{segment}', [CustomerSegmentController::class, 'show'])->middleware('permission:segments.view')->whereNumber('segment')->name('tenant.segments.show');
Route::match(['put', 'patch'], 'segments/{segment}', [CustomerSegmentController::class, 'update'])->middleware('permission:segments.manage')->whereNumber('segment')->name('tenant.segments.update');
Route::delete('segments/{segment}', [CustomerSegmentController::class, 'destroy'])->middleware('permission:segments.manage')->whereNumber('segment')->name('tenant.segments.destroy');
Route::get('segments/{slug}/customers', [CustomerSegmentController::class, 'customers'])->middleware('permission:segments.view')->name('tenant.segments.customers');

Route::get('customer-groups/options', [CustomerGroupController::class, 'options'])->middleware('permission:customer_groups.view')->name('tenant.customer-groups.options');
Route::get('customer-groups', [CustomerGroupController::class, 'index'])->middleware('permission:customer_groups.view')->name('tenant.customer-groups.index');
Route::post('customer-groups', [CustomerGroupController::class, 'store'])->middleware('permission:customer_groups.create')->name('tenant.customer-groups.store');
Route::get('customer-groups/{customer_group}', [CustomerGroupController::class, 'show'])->middleware('permission:customer_groups.show')->whereNumber('customer_group')->name('tenant.customer-groups.show');
Route::match(['put', 'patch'], 'customer-groups/{customer_group}', [CustomerGroupController::class, 'update'])->middleware('permission:customer_groups.update')->whereNumber('customer_group')->name('tenant.customer-groups.update');
Route::delete('customer-groups/{customer_group}', [CustomerGroupController::class, 'destroy'])->middleware('permission:customer_groups.delete')->whereNumber('customer_group')->name('tenant.customer-groups.destroy');

Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('tenant.customers.index');
Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.show')->whereNumber('customer')->name('tenant.customers.show');
Route::match(['put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update')->whereNumber('customer')->name('tenant.customers.update');
Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])->middleware('permission:customers.update')->whereNumber('customer')->name('tenant.customers.status');
