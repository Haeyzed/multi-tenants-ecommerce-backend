<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver location persistence
    |--------------------------------------------------------------------------
    */

    'location' => [
        'min_persist_seconds' => (int) env('DRIVER_LOCATION_MIN_PERSIST_SECONDS', 5),
        'broadcast_throttle_seconds' => (int) env('DRIVER_LOCATION_BROADCAST_THROTTLE_SECONDS', 4),
        'retention_days' => (int) env('DRIVER_LOCATION_RETENTION_DAYS', 14),
    ],

];
