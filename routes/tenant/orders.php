<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Commerce\InvoiceController;
use App\Http\Controllers\Tenant\Commerce\OrderController;
use App\Http\Controllers\Tenant\Commerce\OrderReturnController;
use Illuminate\Support\Facades\Route;

Route::get('orders', [OrderController::class, 'index'])->middleware('permission:orders.view')->name('tenant.orders.index');
Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.show')->whereNumber('order')->name('tenant.orders.show');
Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:orders.update')->whereNumber('order')->name('tenant.orders.status');
Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('permission:orders.cancel')->whereNumber('order')->name('tenant.orders.cancel');
Route::post('orders/{order}/invoice', [InvoiceController::class, 'generateForOrder'])->middleware('permission:invoices.generate')->whereNumber('order')->name('tenant.orders.invoice.generate');

Route::get('returns', [OrderReturnController::class, 'index'])->middleware('permission:returns.view')->name('tenant.returns.index');
Route::get('returns/{order_return}', [OrderReturnController::class, 'show'])->middleware('permission:returns.view')->whereNumber('order_return')->name('tenant.returns.show');
Route::post('returns/{order_return}/approve', [OrderReturnController::class, 'approve'])->middleware('permission:returns.approve')->whereNumber('order_return')->name('tenant.returns.approve');
Route::post('returns/{order_return}/reject', [OrderReturnController::class, 'reject'])->middleware('permission:returns.reject')->whereNumber('order_return')->name('tenant.returns.reject');
Route::post('returns/{order_return}/receive', [OrderReturnController::class, 'markReceived'])->middleware('permission:returns.inspect')->whereNumber('order_return')->name('tenant.returns.receive');
Route::post('returns/{order_return}/inspect', [OrderReturnController::class, 'startInspection'])->middleware('permission:returns.inspect')->whereNumber('order_return')->name('tenant.returns.inspect');
Route::post('return-items/{order_return_item}/inspect', [OrderReturnController::class, 'inspectItem'])->middleware('permission:returns.inspect')->whereNumber('order_return_item')->name('tenant.return-items.inspect');
Route::post('returns/{order_return}/approve-refund', [OrderReturnController::class, 'approveForRefund'])->middleware('permission:returns.complete')->whereNumber('order_return')->name('tenant.returns.approve-refund');
Route::post('returns/{order_return}/process-refund', [OrderReturnController::class, 'processRefund'])->middleware('permission:returns.complete')->whereNumber('order_return')->name('tenant.returns.process-refund');
