<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Notifications
    |--------------------------------------------------------------------------
    |
    | When true, NotificationService dispatches SendNotificationJob instead of
    | delivering synchronously.
    |
    */

    'queue' => (bool) env('NOTIFICATIONS_QUEUE', true),

    /*
    |--------------------------------------------------------------------------
    | Subscription lifecycle reminders
    |--------------------------------------------------------------------------
    */

    'subscription' => [
        'expiring_days' => (int) env('SUBSCRIPTION_EXPIRING_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    */

    'fcm' => [
        'enabled' => (bool) env('FCM_ENABLED', false),
        'project_id' => env('FCM_PROJECT_ID'),
        'client_email' => env('FCM_CLIENT_EMAIL'),
        'private_key' => env('FCM_PRIVATE_KEY'),
        'timeout' => (int) env('FCM_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | Select a driver with SMS_DRIVER. Keep SMS_ENABLED=false until credentials
    | are configured; NullSmsProvider is used as the safe default.
    |
    | Drivers: null, twilio, vonage, messagebird, amazon_sns (sns), termii,
    | africastalking, bulksms, hubtel
    |
    */

    'sms' => [
        'enabled' => (bool) env('SMS_ENABLED', false),
        'default' => env('SMS_DRIVER', 'null'),
        'from' => env('SMS_FROM'),
        'timeout' => (int) env('SMS_TIMEOUT', 15),
        'connect_timeout' => (int) env('SMS_CONNECT_TIMEOUT', 5),

        'drivers' => [

            'null' => [],

            'twilio' => [
                'account_sid' => env('TWILIO_ACCOUNT_SID'),
                'auth_token' => env('TWILIO_AUTH_TOKEN'),
                'from' => env('TWILIO_FROM', env('SMS_FROM')),
                'base_url' => env('TWILIO_BASE_URL', 'https://api.twilio.com'),
            ],

            'vonage' => [
                'api_key' => env('VONAGE_API_KEY'),
                'api_secret' => env('VONAGE_API_SECRET'),
                'from' => env('VONAGE_FROM', env('SMS_FROM')),
                'base_url' => env('VONAGE_BASE_URL', 'https://rest.nexmo.com'),
            ],

            'messagebird' => [
                'access_key' => env('MESSAGEBIRD_ACCESS_KEY'),
                'from' => env('MESSAGEBIRD_FROM', env('SMS_FROM')),
                'base_url' => env('MESSAGEBIRD_BASE_URL', 'https://rest.messagebird.com'),
            ],

            'amazon_sns' => [
                'access_key_id' => env('AWS_SNS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
                'secret_access_key' => env('AWS_SNS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
                'region' => env('AWS_SNS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
                'from' => env('AWS_SNS_SENDER_ID', env('SMS_FROM')),
            ],

            'termii' => [
                'api_key' => env('TERMII_API_KEY'),
                'from' => env('TERMII_FROM', env('SMS_FROM')),
                'channel' => env('TERMII_CHANNEL', 'generic'),
                'type' => env('TERMII_TYPE', 'plain'),
                'base_url' => env('TERMII_BASE_URL', 'https://api.ng.termii.com'),
            ],

            'africastalking' => [
                'username' => env('AFRICASTALKING_USERNAME'),
                'api_key' => env('AFRICASTALKING_API_KEY'),
                'from' => env('AFRICASTALKING_FROM', env('SMS_FROM')),
                'base_url' => env('AFRICASTALKING_BASE_URL', 'https://api.africastalking.com'),
            ],

            'bulksms' => [
                'token_id' => env('BULKSMS_TOKEN_ID'),
                'token_secret' => env('BULKSMS_TOKEN_SECRET'),
                'from' => env('BULKSMS_FROM', env('SMS_FROM')),
                'base_url' => env('BULKSMS_BASE_URL', 'https://api.bulksms.com'),
            ],

            'hubtel' => [
                'client_id' => env('HUBTEL_CLIENT_ID'),
                'client_secret' => env('HUBTEL_CLIENT_SECRET'),
                'from' => env('HUBTEL_FROM', env('SMS_FROM')),
                'base_url' => env('HUBTEL_BASE_URL', 'https://sms.hubtel.com'),
            ],

        ],
    ],

];
