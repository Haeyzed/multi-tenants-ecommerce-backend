<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Shipping\ShipmentController;
use App\Http\Controllers\Tenant\Shipping\ShippingMethodController;
use Illuminate\Support\Facades\Route;

Route::get('shipping-methods/options', [ShippingMethodController::class, 'options'])->middleware('permission:shipping.view')->name('tenant.shipping-methods.options');
Route::get('shipping-methods', [ShippingMethodController::class, 'index'])->middleware('permission:shipping.view')->name('tenant.shipping-methods.index');
Route::post('shipping-methods', [ShippingMethodController::class, 'store'])->middleware('permission:shipping.manage')->name('tenant.shipping-methods.store');
Route::get('shipping-methods/{shipping_method}', [ShippingMethodController::class, 'show'])->middleware('permission:shipping.view')->whereNumber('shipping_method')->name('tenant.shipping-methods.show');
Route::match(['put', 'patch'], 'shipping-methods/{shipping_method}', [ShippingMethodController::class, 'update'])->middleware('permission:shipping.manage')->whereNumber('shipping_method')->name('tenant.shipping-methods.update');
Route::delete('shipping-methods/{shipping_method}', [ShippingMethodController::class, 'destroy'])->middleware('permission:shipping.manage')->whereNumber('shipping_method')->name('tenant.shipping-methods.destroy');

Route::get('shipments', [ShipmentController::class, 'index'])->middleware('permission:shipments.view')->name('tenant.shipments.index');
Route::post('shipments', [ShipmentController::class, 'store'])->middleware('permission:shipments.manage')->name('tenant.shipments.store');
Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->middleware('permission:shipments.view')->whereNumber('shipment')->name('tenant.shipments.show');
Route::patch('shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])->middleware('permission:shipments.manage')->whereNumber('shipment')->name('tenant.shipments.status');
