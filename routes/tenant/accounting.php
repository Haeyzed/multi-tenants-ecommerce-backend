<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Accounting\AccountController;
use App\Http\Controllers\Tenant\Accounting\AccountingReportController;
use App\Http\Controllers\Tenant\Accounting\JournalEntryController;
use Illuminate\Support\Facades\Route;

Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:accounting.view')->name('tenant.accounts.index');
Route::get('accounting/trial-balance', [AccountingReportController::class, 'trialBalance'])->middleware('permission:accounting.view')->name('tenant.accounting.trial-balance');
Route::get('accounting/accounts/{account}/ledger', [AccountingReportController::class, 'ledger'])->middleware('permission:accounting.view')->whereNumber('account')->name('tenant.accounting.accounts.ledger');
Route::get('accounting/accounts/{account}/balance', [AccountingReportController::class, 'balance'])->middleware('permission:accounting.view')->whereNumber('account')->name('tenant.accounting.accounts.balance');
Route::get('journal-entries', [JournalEntryController::class, 'index'])->middleware('permission:accounting.view')->name('tenant.journal-entries.index');
Route::get('journal-entries/{journal_entry}', [JournalEntryController::class, 'show'])->middleware('permission:accounting.view')->whereNumber('journal_entry')->name('tenant.journal-entries.show');
Route::post('journal-entries', [JournalEntryController::class, 'store'])->middleware('permission:journal_entries.create')->name('tenant.journal-entries.store');
Route::post('journal-entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse'])->middleware('permission:journal_entries.reverse')->whereNumber('journal_entry')->name('tenant.journal-entries.reverse');
