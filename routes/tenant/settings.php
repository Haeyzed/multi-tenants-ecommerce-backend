<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings/{domain}', [SettingsController::class, 'show'])->middleware('permission:settings.view')->name('tenant.settings.show');
Route::match(['put', 'patch'], 'settings/{domain}', [SettingsController::class, 'update'])->middleware('permission:settings.update')->name('tenant.settings.update');
