<?php

declare(strict_types=1);

use App\Services\Tenant\HR\Meetings\Providers\FakeInterviewMeetingProvider;
use App\Services\Tenant\HR\Meetings\Providers\GoogleMeetInterviewMeetingProvider;
use App\Services\Tenant\HR\Meetings\Providers\ManualInterviewMeetingProvider;
use App\Services\Tenant\HR\Meetings\Providers\MicrosoftTeamsInterviewMeetingProvider;
use App\Services\Tenant\HR\Meetings\Providers\ZoomInterviewMeetingProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Default meeting provider
    |--------------------------------------------------------------------------
    |
    | Used only when a tenant has not set hr.interviews.default_provider.
    | Do not default tenants to Zoom.
    |
    */

    'default' => 'manual',

    'timeout' => (int) env('INTERVIEW_MEETING_TIMEOUT', 15),
    'connect_timeout' => (int) env('INTERVIEW_MEETING_CONNECT_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | Register a new provider by adding a class here. Interview business logic
    | resolves drivers through InterviewMeetingManager and never names them.
    |
    */

    'drivers' => [
        'manual' => ManualInterviewMeetingProvider::class,
        'zoom' => ZoomInterviewMeetingProvider::class,
        'google_meet' => GoogleMeetInterviewMeetingProvider::class,
        'microsoft_teams' => MicrosoftTeamsInterviewMeetingProvider::class,
        'fake' => FakeInterviewMeetingProvider::class,
    ],

    'zoom' => [
        'oauth_url' => env('ZOOM_OAUTH_URL', 'https://zoom.us/oauth/token'),
        'base_url' => env('ZOOM_API_BASE_URL', 'https://api.zoom.us/v2'),
    ],

    'google_meet' => [
        'token_url' => env('GOOGLE_OAUTH_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'calendar_base_url' => env('GOOGLE_CALENDAR_BASE_URL', 'https://www.googleapis.com/calendar/v3'),
    ],

    'microsoft_teams' => [
        'token_url' => env('MICROSOFT_OAUTH_TOKEN_URL', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token'),
        'graph_base_url' => env('MICROSOFT_GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'),
    ],

];
