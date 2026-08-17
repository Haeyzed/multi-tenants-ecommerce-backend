<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\Settings\PlatformSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings/{domain}', [PlatformSettingsController::class, 'show'])->middleware('permission:settings.view')->name('landlord.settings.show');
Route::match(['put', 'patch'], 'settings/{domain}', [PlatformSettingsController::class, 'update'])->middleware('permission:settings.update')->name('landlord.settings.update');
