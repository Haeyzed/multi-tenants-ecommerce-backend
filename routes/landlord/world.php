<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\World\CityController;
use App\Http\Controllers\Landlord\World\CountryController;
use App\Http\Controllers\Landlord\World\CurrencyController;
use App\Http\Controllers\Landlord\World\GeolocateController;
use App\Http\Controllers\Landlord\World\LanguageController;
use App\Http\Controllers\Landlord\World\StateController;
use App\Http\Controllers\Landlord\World\TimezoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('world')->middleware('central')->name('landlord.world.')->group(function (): void {
    Route::get('geolocate/ip', [GeolocateController::class, 'ip'])->name('geolocate.ip');
    Route::get('geolocate', [GeolocateController::class, 'index'])->name('geolocate');

    Route::get('countries/options', [CountryController::class, 'options'])->name('countries.options');
    Route::get('countries', [CountryController::class, 'index'])->name('countries.index');
    Route::get('countries/{country}', [CountryController::class, 'show'])->whereNumber('country')->name('countries.show');

    Route::get('states/options', [StateController::class, 'options'])->name('states.options');
    Route::get('states', [StateController::class, 'index'])->name('states.index');
    Route::get('states/{state}', [StateController::class, 'show'])->whereNumber('state')->name('states.show');

    Route::get('cities/options', [CityController::class, 'options'])->name('cities.options');
    Route::get('cities', [CityController::class, 'index'])->name('cities.index');
    Route::get('cities/{city}', [CityController::class, 'show'])->whereNumber('city')->name('cities.show');

    Route::get('currencies/options', [CurrencyController::class, 'options'])->name('currencies.options');
    Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
    Route::get('currencies/{currency}', [CurrencyController::class, 'show'])->whereNumber('currency')->name('currencies.show');

    Route::get('timezones/options', [TimezoneController::class, 'options'])->name('timezones.options');
    Route::get('timezones', [TimezoneController::class, 'index'])->name('timezones.index');
    Route::get('timezones/{timezone}', [TimezoneController::class, 'show'])->whereNumber('timezone')->name('timezones.show');

    Route::get('languages/options', [LanguageController::class, 'options'])->name('languages.options');
    Route::get('languages', [LanguageController::class, 'index'])->name('languages.index');
    Route::get('languages/{language}', [LanguageController::class, 'show'])->whereNumber('language')->name('languages.show');
});
