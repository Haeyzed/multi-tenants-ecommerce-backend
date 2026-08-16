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
    | Optional payment method labels per driver
    |--------------------------------------------------------------------------
    */

    'methods' => [
        'paystack' => ['card', 'bank_transfer', 'ussd', 'qr'],
        'flutterwave' => ['card', 'bank_transfer', 'ussd', 'mobile_money'],
        'monnify' => ['card', 'bank_transfer', 'ussd'],
        'moniepoint' => ['card', 'bank_transfer'],
        'fake' => ['card', 'bank_transfer'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency → driver routing (optional)
    |--------------------------------------------------------------------------
    |
    | Uncomment to prefer a specific driver for a currency when tenant
    | gateway settings do not resolve one:
    |
    | 'routing' => [
    |     'NGN' => 'paystack',
    |     'USD' => 'flutterwave',
    |     'GHS' => 'paystack',
    | ],
    |
    */

    // 'routing' => [
    //     'NGN' => 'paystack',
    //     'USD' => 'flutterwave',
    // ],

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

        'flutterwave' => [
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
            'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
            'callback_url' => env('FLUTTERWAVE_CALLBACK_URL'),
            'currencies' => ['NGN', 'USD', 'GHS', 'KES', 'ZAR'],
            'timeout' => (int) env('FLUTTERWAVE_TIMEOUT', 15),
            'connect_timeout' => (int) env('FLUTTERWAVE_CONNECT_TIMEOUT', 5),
        ],

        'monnify' => [
            'api_key' => env('MONNIFY_API_KEY'),
            'secret_key' => env('MONNIFY_SECRET_KEY'),
            'contract_code' => env('MONNIFY_CONTRACT_CODE'),
            'base_url' => env('MONNIFY_BASE_URL', 'https://sandbox.monnify.com'),
            'callback_url' => env('MONNIFY_CALLBACK_URL'),
            'currencies' => ['NGN'],
            'timeout' => (int) env('MONNIFY_TIMEOUT', 15),
            'connect_timeout' => (int) env('MONNIFY_CONNECT_TIMEOUT', 5),
            'token_cache_minutes' => (int) env('MONNIFY_TOKEN_CACHE_MINUTES', 50),
        ],

        'moniepoint' => [
            // Scaffold only — fill when official Moniepoint API docs/credentials are available.
            'api_key' => env('MONIEPOINT_API_KEY'),
            'secret_key' => env('MONIEPOINT_SECRET_KEY'),
            'base_url' => env('MONIEPOINT_BASE_URL'),
            'webhook_secret' => env('MONIEPOINT_WEBHOOK_SECRET'),
            'callback_url' => env('MONIEPOINT_CALLBACK_URL'),
            'currencies' => ['NGN'],
            'timeout' => (int) env('MONIEPOINT_TIMEOUT', 15),
            'connect_timeout' => (int) env('MONIEPOINT_CONNECT_TIMEOUT', 5),
        ],

        'fake' => [
            'currencies' => ['NGN', 'USD'],
            'authorization_url' => env('FAKE_PAYMENT_AUTH_URL', 'https://payments.test/fake/authorize'),
        ],

    ],

];
