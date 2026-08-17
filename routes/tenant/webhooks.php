<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Commerce\PaymentWebhookController;
use App\Http\Controllers\Tenant\Shipping\CarrierWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('payments/webhooks/paystack', [PaymentWebhookController::class, 'paystack'])
    ->middleware('throttle:120,1')
    ->name('tenant.payments.webhooks.paystack');

Route::post('payments/webhooks/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->where('provider', 'paystack|flutterwave|monnify|moniepoint|fake')
    ->name('tenant.payments.webhooks.provider');

Route::post('webhooks/shipping/{carrier}', CarrierWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('tenant.webhooks.shipping.carrier');
