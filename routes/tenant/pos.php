<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Pos\PosCashDrawerController;
use App\Http\Controllers\Tenant\Pos\PosCatalogController;
use App\Http\Controllers\Tenant\Pos\PosReportController;
use App\Http\Controllers\Tenant\Pos\PosSaleController;
use App\Http\Controllers\Tenant\Pos\PosSessionController;
use App\Http\Controllers\Tenant\Pos\PosTerminalController;
use Illuminate\Support\Facades\Route;

Route::middleware('feature:pos')->prefix('pos')->group(function (): void {
    Route::get('terminals/options', [PosTerminalController::class, 'options'])->middleware('permission:pos.view')->name('tenant.pos.terminals.options');
    Route::get('terminals', [PosTerminalController::class, 'index'])->middleware('permission:pos.view')->name('tenant.pos.terminals.index');
    Route::post('terminals', [PosTerminalController::class, 'store'])->middleware('permission:pos.terminals.manage')->name('tenant.pos.terminals.store');
    Route::get('terminals/{pos_terminal}', [PosTerminalController::class, 'show'])->middleware('permission:pos.view')->whereNumber('pos_terminal')->name('tenant.pos.terminals.show');
    Route::match(['put', 'patch'], 'terminals/{pos_terminal}', [PosTerminalController::class, 'update'])->middleware('permission:pos.terminals.manage')->whereNumber('pos_terminal')->name('tenant.pos.terminals.update');
    Route::delete('terminals/{pos_terminal}', [PosTerminalController::class, 'destroy'])->middleware('permission:pos.terminals.manage')->whereNumber('pos_terminal')->name('tenant.pos.terminals.destroy');

    Route::get('sessions', [PosSessionController::class, 'index'])->middleware('permission:pos.view')->name('tenant.pos.sessions.index');
    Route::post('sessions/open', [PosSessionController::class, 'open'])->middleware('permission:pos.session.open')->name('tenant.pos.sessions.open');
    Route::get('sessions/{pos_session}', [PosSessionController::class, 'show'])->middleware('permission:pos.view')->whereNumber('pos_session')->name('tenant.pos.sessions.show');
    Route::post('sessions/{pos_session}/close', [PosSessionController::class, 'close'])->middleware('permission:pos.session.close')->whereNumber('pos_session')->name('tenant.pos.sessions.close');

    Route::post('sessions/{pos_session}/cash-in', [PosCashDrawerController::class, 'cashIn'])->middleware('permission:pos.cash_in')->whereNumber('pos_session')->name('tenant.pos.sessions.cash-in');
    Route::post('sessions/{pos_session}/cash-out', [PosCashDrawerController::class, 'cashOut'])->middleware('permission:pos.cash_out')->whereNumber('pos_session')->name('tenant.pos.sessions.cash-out');

    Route::post('sessions/{pos_session}/sales', [PosSaleController::class, 'store'])->middleware('permission:pos.sell')->whereNumber('pos_session')->name('tenant.pos.sales.store');
    Route::post('orders/{order}/pos-refund', [PosSaleController::class, 'refund'])->middleware('permission:pos.refund')->whereNumber('order')->name('tenant.pos.sales.refund');

    Route::get('catalog/search', [PosCatalogController::class, 'search'])->middleware('permission:pos.view')->name('tenant.pos.catalog.search');
    Route::get('catalog/barcode', [PosCatalogController::class, 'barcode'])->middleware('permission:pos.view')->name('tenant.pos.catalog.barcode');

    Route::get('reports/sessions/{pos_session}', [PosReportController::class, 'sessionSummary'])->middleware('permission:pos.reports.view')->whereNumber('pos_session')->name('tenant.pos.reports.session');
    Route::get('reports/sales-by-terminal', [PosReportController::class, 'salesByTerminal'])->middleware('permission:pos.reports.view')->name('tenant.pos.reports.sales-by-terminal');
    Route::get('reports/sales-by-cashier', [PosReportController::class, 'salesByCashier'])->middleware('permission:pos.reports.view')->name('tenant.pos.reports.sales-by-cashier');
    Route::get('reports/payment-methods', [PosReportController::class, 'paymentMethodTotals'])->middleware('permission:pos.reports.view')->name('tenant.pos.reports.payment-methods');
});
