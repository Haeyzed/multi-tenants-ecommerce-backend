<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('media/options', [MediaController::class, 'options'])->name('landlord.media.options');
Route::apiResource('media', MediaController::class)
    ->parameters(['media' => 'media'])
    ->names('landlord.media')
    ->whereNumber('media');
