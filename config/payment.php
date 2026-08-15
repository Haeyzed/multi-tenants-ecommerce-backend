<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway driver used when
    | initializing and verifying payments via the PaymentManager.
    |
    */

    'default' => env('PAYMENT_DRIVER', 'paystack'),

    /*
    |--------------------------------------------------------------------------
    | Payment Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET', env('PAYSTACK_SECRET_KEY')),
            'callback_url' => env('PAYSTACK_CALLBACK_URL'),
            'currencies' => ['NGN', 'GHS', 'ZAR', 'USD'],
            'timeout' => (int) env('PAYSTACK_TIMEOUT', 15),
            'connect_timeout' => (int) env('PAYSTACK_CONNECT_TIMEOUT', 5),
        ],

    ],

];
