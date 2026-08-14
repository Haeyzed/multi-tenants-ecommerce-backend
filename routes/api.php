<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landlord (Central) API Routes
|--------------------------------------------------------------------------
|
| API routes for the central / landlord application. Prefixed with /api
| and loaded with the "api" middleware group.
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
