<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Procurement\PurchaseOrderController;
use App\Http\Controllers\Tenant\Procurement\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('suppliers/options', [SupplierController::class, 'options'])->middleware('permission:suppliers.view')->name('tenant.suppliers.options');
Route::get('suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view')->name('tenant.suppliers.index');
Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create')->name('tenant.suppliers.store');
Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.show')->whereNumber('supplier')->name('tenant.suppliers.show');
Route::match(['put', 'patch'], 'suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.update')->whereNumber('supplier')->name('tenant.suppliers.update');
Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete')->whereNumber('supplier')->name('tenant.suppliers.destroy');

Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:procurement.view')->name('tenant.purchase-orders.index');
Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:procurement.create')->name('tenant.purchase-orders.store');
Route::get('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->middleware('permission:procurement.view')->whereNumber('purchase_order')->name('tenant.purchase-orders.show');
Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:procurement.approve')->whereNumber('purchase_order')->name('tenant.purchase-orders.approve');
Route::post('purchase-orders/{purchase_order}/mark-ordered', [PurchaseOrderController::class, 'markOrdered'])->middleware('permission:procurement.update')->whereNumber('purchase_order')->name('tenant.purchase-orders.mark-ordered');
Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:procurement.update')->whereNumber('purchase_order')->name('tenant.purchase-orders.cancel');
Route::post('purchase-orders/{purchase_order}/close', [PurchaseOrderController::class, 'close'])->middleware('permission:procurement.update')->whereNumber('purchase_order')->name('tenant.purchase-orders.close');
Route::get('purchase-orders/{purchase_order}/receipts', [PurchaseOrderController::class, 'receipts'])->middleware('permission:procurement.view')->whereNumber('purchase_order')->name('tenant.purchase-orders.receipts');
Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:procurement.receive')->whereNumber('purchase_order')->name('tenant.purchase-orders.receive');
