<?php

declare(strict_types=1);

use App\Models\Landlord\User as LandlordUser;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use App\Models\Tenant\User as TenantUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'landlord'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'landlord_users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'landlord_users',
        ],
        'landlord' => [
            'driver' => 'session',
            'provider' => 'landlord_users',
        ],
        'tenant' => [
            'driver' => 'session',
            'provider' => 'tenant_users',
        ],
        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
        'driver' => [
            'driver' => 'session',
            'provider' => 'drivers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'landlord_users' => [
            'driver' => 'eloquent',
            'model' => LandlordUser::class,
        ],
        'tenant_users' => [
            'driver' => 'eloquent',
            'model' => TenantUser::class,
        ],
        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],
        'drivers' => [
            'driver' => 'eloquent',
            'model' => Driver::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'landlord_users' => [
            'provider' => 'landlord_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        'tenant_users' => [
            'provider' => 'tenant_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'customers' => [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'drivers' => [
            'provider' => 'drivers',
            'table' => 'driver_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
