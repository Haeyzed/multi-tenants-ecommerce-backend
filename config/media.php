<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Limits (kilobytes for Laravel validation `max:`)
    |--------------------------------------------------------------------------
    */

    'upload_limits' => [
        'image' => (int) env('MEDIA_MAX_IMAGE_KB', 10 * 1024),
        'document' => (int) env('MEDIA_MAX_DOCUMENT_KB', 20 * 1024),
        'video' => (int) env('MEDIA_MAX_VIDEO_KB', 100 * 1024),
        'audio' => (int) env('MEDIA_MAX_AUDIO_KB', 50 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME Types
    |--------------------------------------------------------------------------
    */

    'mimes' => [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
        ],
        'video' => ['video/mp4', 'video/webm'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/wave'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions (secondary check; MIME is primary)
    |--------------------------------------------------------------------------
    */

    'extensions' => [
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
        'video' => ['mp4', 'webm'],
        'audio' => ['mp3', 'wav'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversion Dimensions (width x height, fit without upscaling)
    |--------------------------------------------------------------------------
    */

    'conversions' => [
        'thumb' => ['width' => 150, 'height' => 150],
        'small' => ['width' => 320, 'height' => 320],
        'medium' => ['width' => 640, 'height' => 640],
        'large' => ['width' => 1280, 'height' => 1280],
    ],

];
