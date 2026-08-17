<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('media/options', [MediaController::class, 'options'])->name('tenant.media.options');
Route::apiResource('media', MediaController::class)
    ->parameters(['media' => 'media'])
    ->names('tenant.media')
    ->whereNumber('media');
