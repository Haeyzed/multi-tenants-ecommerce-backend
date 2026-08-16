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
        ],

        /*
        | Future carrier stubs — credential keys only; no HTTP clients yet.
        */

        'dhl' => [
            'label' => 'DHL Express',
            'api_key' => env('DHL_API_KEY'),
            'api_secret' => env('DHL_API_SECRET'),
            'account_number' => env('DHL_ACCOUNT_NUMBER'),
            'base_url' => env('DHL_BASE_URL', 'https://express.api.dhl.com'),
        ],

        'gig' => [
            'label' => 'GIG Logistics',
            'api_key' => env('GIG_API_KEY'),
            'api_secret' => env('GIG_API_SECRET'),
            'merchant_id' => env('GIG_MERCHANT_ID'),
            'base_url' => env('GIG_BASE_URL', 'https://api.giglogistics.com'),
        ],

        'fedex' => [
            'label' => 'FedEx',
            'client_id' => env('FEDEX_CLIENT_ID'),
            'client_secret' => env('FEDEX_CLIENT_SECRET'),
            'account_number' => env('FEDEX_ACCOUNT_NUMBER'),
            'base_url' => env('FEDEX_BASE_URL', 'https://apis.fedex.com'),
        ],

        'ups' => [
            'label' => 'UPS',
            'client_id' => env('UPS_CLIENT_ID'),
            'client_secret' => env('UPS_CLIENT_SECRET'),
            'account_number' => env('UPS_ACCOUNT_NUMBER'),
            'base_url' => env('UPS_BASE_URL', 'https://onlinetools.ups.com'),
        ],

    ],

];
