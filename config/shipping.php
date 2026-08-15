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
    ],

    'drivers' => [

        'fake' => [
            'label' => 'Fake Carrier (testing)',
        ],

    ],

];
