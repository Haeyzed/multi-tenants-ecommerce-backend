<?php

declare(strict_types=1);

use App\Http\Controllers\Webhook\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/{provider}', WebhookController::class)
    ->middleware(['central', 'throttle:120,1'])
    ->name('webhooks.provider');
