<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Carrier
    |--------------------------------------------------------------------------
    */

    'default' => env('SHIPPING_CARRIER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Use Carrier Integrations
    |--------------------------------------------------------------------------
    |
    | When false, ShipmentService uses flat ShippingMethod flow only.
    |
    */

    'use_carriers' => (bool) env('SHIPPING_USE_CARRIERS', false),

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP defaults for future carrier clients
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => (int) env('SHIPPING_HTTP_TIMEOUT', 15),
        'connect_timeout' => (int) env('SHIPPING_HTTP_CONNECT_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping method code => carrier driver mapping
    |--------------------------------------------------------------------------
    */

    'method_carriers' => [
        // 'express' => 'fake',
        // 'dhl_express' => 'dhl',
        // 'gig_standard' => 'gig',
    ],

    'drivers' => [

        'fake' => [
            'label' => 'Fake Carrier (testing)',
            'enabled' => true,
            'environment' => env('FAKE_CARRIER_ENVIRONMENT', 'local'),
            'base_url' => env('FAKE_CARRIER_BASE_URL', 'https://example.test'),
            'webhook_secret' => env('FAKE_CARRIER_WEBHOOK_SECRET'),
            'credentials' => [],
        ],

        /*
        | Carrier stubs — credential keys only; no live HTTP clients yet.
        */

        'dhl' => [
            'label' => 'DHL Express',
            'enabled' => (bool) env('DHL_ENABLED', false),
            'environment' => env('DHL_ENVIRONMENT', 'sandbox'),
            'base_url' => env('DHL_BASE_URL', 'https://express.api.dhl.com'),
            'credentials' => [
                'api_key' => env('DHL_API_KEY'),
                'api_secret' => env('DHL_API_SECRET'),
                'account_number' => env('DHL_ACCOUNT_NUMBER'),
            ],
        ],

        'gig' => [
            'label' => 'GIG Logistics',
            'enabled' => (bool) env('GIG_ENABLED', false),
            'environment' => env('GIG_ENVIRONMENT', 'sandbox'),
            'base_url' => env('GIG_BASE_URL', 'https://api.giglogistics.com'),
            'credentials' => [
                'api_key' => env('GIG_API_KEY'),
                'api_secret' => env('GIG_API_SECRET'),
                'merchant_id' => env('GIG_MERCHANT_ID'),
            ],
        ],

        'fedex' => [
            'label' => 'FedEx',
            'enabled' => (bool) env('FEDEX_ENABLED', false),
            'environment' => env('FEDEX_ENVIRONMENT', 'sandbox'),
            'base_url' => env('FEDEX_BASE_URL', 'https://apis.fedex.com'),
            'credentials' => [
                'client_id' => env('FEDEX_CLIENT_ID'),
                'client_secret' => env('FEDEX_CLIENT_SECRET'),
                'account_number' => env('FEDEX_ACCOUNT_NUMBER'),
            ],
        ],

        'ups' => [
            'label' => 'UPS',
            'enabled' => (bool) env('UPS_ENABLED', false),
            'environment' => env('UPS_ENVIRONMENT', 'sandbox'),
            'base_url' => env('UPS_BASE_URL', 'https://onlinetools.ups.com'),
            'credentials' => [
                'client_id' => env('UPS_CLIENT_ID'),
                'client_secret' => env('UPS_CLIENT_SECRET'),
                'account_number' => env('UPS_ACCOUNT_NUMBER'),
            ],
        ],

        'local' => [
            'label' => 'Local / Own Fleet',
            'enabled' => (bool) env('LOCAL_CARRIER_ENABLED', false),
            'environment' => env('LOCAL_CARRIER_ENVIRONMENT', 'local'),
            'base_url' => env('LOCAL_CARRIER_BASE_URL'),
            'credentials' => [
                'api_key' => env('LOCAL_CARRIER_API_KEY'),
            ],
        ],

    ],

];
