<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Delivery\DeliveryController;
use Illuminate\Support\Facades\Route;

Route::get('deliveries', [DeliveryController::class, 'index'])->middleware('permission:deliveries.view')->name('tenant.deliveries.index');
Route::post('deliveries', [DeliveryController::class, 'store'])->middleware('permission:deliveries.manage')->name('tenant.deliveries.store');
Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->middleware('permission:deliveries.view')->whereNumber('delivery')->name('tenant.deliveries.show');
Route::post('deliveries/{delivery}/assign', [DeliveryController::class, 'assign'])->middleware('permission:deliveries.manage')->whereNumber('delivery')->name('tenant.deliveries.assign');
Route::post('deliveries/{delivery}/assign-automatic', [DeliveryController::class, 'assignAutomatic'])->middleware('permission:deliveries.manage')->whereNumber('delivery')->name('tenant.deliveries.assign-automatic');
Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->middleware('permission:deliveries.manage')->whereNumber('delivery')->name('tenant.deliveries.cancel');
Route::post('deliveries/{delivery}/fail', [DeliveryController::class, 'fail'])->middleware('permission:deliveries.manage')->whereNumber('delivery')->name('tenant.deliveries.fail');
